<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Xavier Noguer <xnoguer@php.net>                              |
// | Based on OLE::Storage_Lite by Kawai, Takanori                        |
// +----------------------------------------------------------------------+
//
// $Id$

namespace Xls;

/**
 * OLE package base class.
 *
 * @category Structures
 * @package  OLE
 * @author   Xavier Noguer <xnoguer@php.net>
 * @author   Christian Schmidt <schmidt@php.net>
 */
class OLE
{
    const OLE_PPS_TYPE_ROOT = 5;
    const OLE_PPS_TYPE_DIR = 1;
    const OLE_PPS_TYPE_FILE = 2;
    const OLE_DATA_SIZE_SMALL = 0x1000;
    const OLE_LONG_INT_SIZE = 4;
    const OLE_PPS_SIZE = 0x80;

    public static $instances = array();

    /**
     * The file handle for reading an OLE container
     * @var resource
     */
    public $fileHandle;

    /**
     * Array of PPS's found on the OLE container
     * @var array
     */
    protected $list;

    /**
     * Root directory of OLE container
     * @var \Xls\OLE\PPS\Root
     */
    public $root;

    /**
     * Big Block Allocation Table
     * @var array  (blockId => nextBlockId)
     */
    public $bbat;

    /**
     * Short Block Allocation Table
     * @var array  (blockId => nextBlockId)
     */
    public $sbat;

    /**
     * Size of big blocks. This is usually 512.
     * @var  int  number of octets per block.
     */
    public $bigBlockSize;

    /**
     * @var
     */
    public $bigBlockThreshold;

    /**
     * Size of small blocks. This is usually 64.
     * @var  int  number of octets per block
     */
    public $smallBlockSize;

    /**
     * Creates a new OLE object
     */
    public function __construct()
    {
        $this->list = array();
    }

    /**
     * Destructor
     * Just closes the file handle on the OLE file.
     */
    public function __destruct()
    {
        if (is_resource($this->fileHandle)) {
            fclose($this->fileHandle);
        }
    }

    /**
     * Reads an OLE container from the contents of the file given.
     *
     * @param string $file
     * @throws \Exception
     *
     * @return mixed true on success
     */
    public function read($file)
    {
        $fh = @fopen($file, "r");
        if (!$fh) {
            throw new \Exception("Can't open file $file");
        }
        $this->fileHandle = $fh;

        $signature = fread($fh, 8);
        if ("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" != $signature) {
            throw new \Exception("File doesn't seem to be an OLE container.");
        }
        fseek($fh, 28);
        if (fread($fh, 2) != "\xFE\xFF") {
            // This shouldn't be a problem in practice
            throw new \Exception("Only Little-Endian encoding is supported.");
        }
        // Size of blocks and short blocks in bytes
        $this->bigBlockSize = pow(2, $this->readInt2($fh));
        $this->smallBlockSize = pow(2, $this->readInt2($fh));

        // Skip UID, revision number and version number
        fseek($fh, 44);
        // Number of blocks in Big Block Allocation Table
        $bbatBlockCount = $this->readInt4($fh);

        // Root chain 1st block
        $directoryFirstBlockId = $this->readInt4($fh);

        // Skip unused bytes
        fseek($fh, 56);
        // Streams shorter than this are stored using small blocks
        $this->bigBlockThreshold = $this->readInt4($fh);
        // Block id of first sector in Short Block Allocation Table
        $sbatFirstBlockId = $this->readInt4($fh);
        // Number of blocks in Short Block Allocation Table
        $sbbatBlockCount = $this->readInt4($fh);
        // Block id of first sector in Master Block Allocation Table
        $mbatFirstBlockId = $this->readInt4($fh);
        // Number of blocks in Master Block Allocation Table
        $mbbatBlockCount = $this->readInt4($fh);
        $this->bbat = array();

        // Remaining 4 * 109 bytes of current block is beginning of Master
        // Block Allocation Table
        $mbatBlocks = array();
        for ($i = 0; $i < 109; $i++) {
            $mbatBlocks[] = $this->readInt4($fh);
        }

        // Read rest of Master Block Allocation Table (if any is left)
        $pos = $this->getBlockOffset($mbatFirstBlockId);
        for ($i = 0; $i < $mbbatBlockCount; $i++) {
            fseek($fh, $pos);
            for ($j = 0; $j < $this->bigBlockSize / 4 - 1; $j++) {
                $mbatBlocks[] = $this->readInt4($fh);
            }
            // Last block id in each block points to next block
            $pos = $this->getBlockOffset($this->readInt4($fh));
        }

        // Read Big Block Allocation Table according to chain specified by
        // $mbatBlocks
        for ($i = 0; $i < $bbatBlockCount; $i++) {
            $pos = $this->getBlockOffset($mbatBlocks[$i]);
            fseek($fh, $pos);
            for ($j = 0; $j < $this->bigBlockSize / 4; $j++) {
                $this->bbat[] = $this->readInt4($fh);
            }
        }

        // Read short block allocation table (SBAT)
        $this->sbat = array();
        $shortBlockCount = $sbbatBlockCount * $this->bigBlockSize / 4;
        $sbatFh = $this->getStream($sbatFirstBlockId);
        if (!$sbatFh) {
            // Avoid an infinite loop if ChainedBlockStream.php somehow is
            // missing
            return false;
        }
        for ($blockId = 0; $blockId < $shortBlockCount; $blockId++) {
            $this->sbat[$blockId] = $this->readInt4($sbatFh);
        }
        fclose($sbatFh);

        $this->readPpsWks($directoryFirstBlockId);

        return true;
    }

    /**
     * @param int $blockId block id
     * @return int byte offset from beginning of file
     */
    public function getBlockOffset($blockId)
    {
        return 512 + $blockId * $this->bigBlockSize;
    }

    /**
     * Returns a stream for use with fread() etc. External callers should
     * use OLE\PPS\File::getStream().
     * @param int|\Xls\OLE\PPS $blockIdOrPps block id or PPS
     * @return resource read-only stream
     */
    public function getStream($blockIdOrPps)
    {
        static $isRegistered = false;
        if (!$isRegistered) {
            stream_wrapper_register(
                'ole-chainedblockstream',
                'Xls\OLE\ChainedBlockStream'
            );
            $isRegistered = true;
        }

        // Store current instance in global array, so that it can be accessed
        // in OLE\ChainedBlockStream::stream_open().
        // Object is removed from self::$instances in OLE\Stream::close().
        self::$instances[] = $this;
        $instancesIds = array_keys(self::$instances);
        $instanceId = end($instancesIds);

        $path = 'ole-chainedblockstream://oleInstanceId=' . $instanceId;
        if (is_a($blockIdOrPps, 'Xls\OLE\PPS')) {
            $path .= '&blockId=' . $blockIdOrPps->StartBlock;
            $path .= '&size=' . $blockIdOrPps->Size;
        } else {
            $path .= '&blockId=' . $blockIdOrPps;
        }

        return fopen($path, 'r');
    }

    /**
     * Reads a signed char.
     * @param resource $fh file handle
     * @return int
     */
    protected function readInt1($fh)
    {
        list(, $tmp) = unpack("c", fread($fh, 1));

        return $tmp;
    }

    /**
     * Reads an unsigned short (2 octets).
     * @param resource $fh file handle
     * @return int
     */
    protected function readInt2($fh)
    {
        list(, $tmp) = unpack("v", fread($fh, 2));

        return $tmp;
    }

    /**
     * Reads an unsigned long (4 octets).
     * @param $fh resource file handle
     * @return int
     */
    protected function readInt4($fh)
    {
        list(, $tmp) = unpack("V", fread($fh, 4));

        return $tmp;
    }

    /**
     * Gets information about all PPS's on the OLE container from the PPS WK's
     * creates an OLE\PPS object for each one.
     *
     * @param integer $blockId the block id of the first block
     * @return mixed true on success
     */
    protected function readPpsWks($blockId)
    {
        $fh = $this->getStream($blockId);
        for ($pos = 0; true; $pos += 128) {
            fseek($fh, $pos, SEEK_SET);
            $nameUtf16 = fread($fh, 64);
            $nameLength = $this->readInt2($fh);
            $nameUtf16 = substr($nameUtf16, 0, $nameLength - 2);
            // Simple conversion from UTF-16LE to ISO-8859-1
            $name = str_replace("\x00", "", $nameUtf16);
            $type = $this->readInt1($fh);

            switch ($type) {
                case self::OLE_PPS_TYPE_ROOT:
                    $pps = new OLE\PPS\Root();
                    $this->root = $pps;
                    break;
                case self::OLE_PPS_TYPE_DIR:
                    $pps = new OLE\PPS();
                    break;
                case self::OLE_PPS_TYPE_FILE:
                    $pps = new OLE\PPS\File($name);
                    break;
                default:
                    $pps = null;
                    continue;
            }

            fseek($fh, 1, SEEK_CUR);

            $pps->Type = $type;
            $pps->Name = $name;
            $pps->PrevPps = $this->readInt4($fh);
            $pps->NextPps = $this->readInt4($fh);
            $pps->DirPps = $this->readInt4($fh);

            fseek($fh, 20, SEEK_CUR);

            $pps->Time1st = OLE::ole2LocalDate(fread($fh, 8));
            $pps->Time2nd = OLE::ole2LocalDate(fread($fh, 8));
            $pps->StartBlock = $this->readInt4($fh);
            $pps->Size = $this->readInt4($fh);
            $pps->No = count($this->list);
            $this->list[] = $pps;

            // check if the PPS tree (starting from root) is complete
            if (isset($this->root)
                && $this->ppsTreeComplete($this->root->No)
            ) {
                break;
            }
        }
        fclose($fh);

        // Initialize $pps->children on directories
        foreach ($this->list as $pps) {
            if ($pps->Type == self::OLE_PPS_TYPE_DIR || $pps->Type == self::OLE_PPS_TYPE_ROOT) {
                $nos = array($pps->DirPps);
                $pps->children = array();
                while ($nos) {
                    $no = array_pop($nos);
                    if ($no != -1) {
                        $childPps = $this->list[$no];
                        $nos[] = $childPps->PrevPps;
                        $nos[] = $childPps->NextPps;
                        $pps->children[] = $childPps;
                    }
                }
            }
        }

        return true;
    }

    /**
     * It checks whether the PPS tree is complete (all PPS's read)
     * starting with the given PPS (not necessarily root)
     *
     * @param integer $index The index of the PPS from which we are checking
     * @return boolean Whether the PPS tree for the given PPS is complete
     */
    protected function ppsTreeComplete($index)
    {
        return isset($this->list[$index])
        && ($pps = $this->list[$index])
        && ($pps->PrevPps == -1
            || $this->ppsTreeComplete($pps->PrevPps))
        && ($pps->NextPps == -1
            || $this->ppsTreeComplete($pps->NextPps))
        && ($pps->DirPps == -1
            || $this->ppsTreeComplete($pps->DirPps));
    }

    /**
     * Checks whether a PPS is a File PPS or not.
     * If there is no PPS for the index given, it will return false.
     * @param integer $index The index for the PPS
     * @return bool true if it's a File PPS, false otherwise
     */
    public function isFile($index)
    {
        if (isset($this->list[$index])) {
            return ($this->list[$index]->Type == self::OLE_PPS_TYPE_FILE);
        }

        return false;
    }

    /**
     * Checks whether a PPS is a Root PPS or not.
     * If there is no PPS for the index given, it will return false.
     * @param integer $index The index for the PPS.
     * @return bool true if it's a Root PPS, false otherwise
     */
    public function isRoot($index)
    {
        if (isset($this->list[$index])) {
            return ($this->list[$index]->Type == self::OLE_PPS_TYPE_ROOT);
        }

        return false;
    }

    /**
     * Gives the total number of PPS's found in the OLE container.
     * @return integer The total number of PPS's found in the OLE container
     */
    public function ppsTotal()
    {
        return count($this->list);
    }

    /**
     * Gets data from a PPS
     * If there is no PPS for the index given, it will return an empty string.
     * @param integer $index    The index for the PPS
     * @param integer $position The position from which to start reading
     *                          (relative to the PPS)
     * @param integer $length   The amount of bytes to read (at most)
     * @return string The binary string containing the data requested
     * @see OLE_PPS_File::getStream()
     */
    public function getData($index, $position, $length)
    {
        // if position is not valid return empty string
        if (!isset($this->list[$index])
            || $position >= $this->list[$index]->Size
            || $position < 0
        ) {
            return '';
        }

        $fh = $this->getStream($this->list[$index]);
        $data = stream_get_contents($fh, $length, $position);
        fclose($fh);

        return $data;
    }

    /**
     * Gets the data length from a PPS
     * If there is no PPS for the index given, it will return 0.
     * @param integer $index The index for the PPS
     * @return integer The amount of bytes in data the PPS has
     */
    public function getDataLength($index)
    {
        if (isset($this->list[$index])) {
            return $this->list[$index]->Size;
        }

        return 0;
    }

    /**
     * Utility function to transform ASCII text to Unicode
     *
     * @param string $ascii The ASCII string to transform
     * @return string The string in Unicode
     */
    public static function asc2Ucs($ascii)
    {
        $rawname = '';
        $len = strlen($ascii);
        for ($i = 0; $i < $len; $i++) {
            $rawname .= $ascii{$i} . "\x00";
        }

        return $rawname;
    }

    /**
     * Utility function
     * Returns a string for the OLE container with the date given
     *
     * @param integer $date A timestamp
     *
     * @return string The string for the OLE container
     */
    public static function localDate2OLE($date = null)
    {
        if (!isset($date)) {
            return "\x00\x00\x00\x00\x00\x00\x00\x00";
        }

        // factor used for separating numbers into 4 bytes parts
        $factor = pow(2, 32);

        // days from 1-1-1601 until the beggining of UNIX era
        $days = 134774;
        // calculate seconds
        $big_date = $days * 24 * 3600 +
            gmmktime(
                date("H", $date),
                date("i", $date),
                date("s", $date),
                date("m", $date),
                date("d", $date),
                date("Y", $date)
            );
        // multiply just to make MS happy
        $big_date *= 10000000;

        $high_part = floor($big_date / $factor);
        // lower 4 bytes
        $low_part = floor((($big_date / $factor) - $high_part) * $factor);

        // Make HEX string
        $res = '';

        for ($i = 0; $i < 4; $i++) {
            $hex = $low_part % 0x100;
            $res .= pack('c', $hex);
            $low_part /= 0x100;
        }

        for ($i = 0; $i < 4; $i++) {
            $hex = $high_part % 0x100;
            $res .= pack('c', $hex);
            $high_part /= 0x100;
        }

        return $res;
    }

    /**
     * Returns a timestamp from an OLE container's date
     * @param integer $string A binary string with the encoded date
     * @throws \Exception
     *
     * @return string The timestamp corresponding to the string
     */
    public static function ole2LocalDate($string)
    {
        if (strlen($string) != 8) {
            throw new \Exception("Expecting 8 byte string");
        }

        // factor used for separating numbers into 4 bytes parts
        $factor = pow(2, 32);
        $high_part = 0;
        for ($i = 0; $i < 4; $i++) {
            list(, $high_part) = unpack('C', $string{(7 - $i)});
            if ($i < 3) {
                $high_part *= 0x100;
            }
        }
        $low_part = 0;
        for ($i = 4; $i < 8; $i++) {
            list(, $low_part) = unpack('C', $string{(7 - $i)});
            if ($i < 7) {
                $low_part *= 0x100;
            }
        }
        $big_date = ($high_part * $factor) + $low_part;
        // translate to seconds
        $big_date /= 10000000;

        // days from 1-1-1601 until the beggining of UNIX era
        $days = 134774;

        // translate to seconds from beggining of UNIX era
        $big_date -= $days * 24 * 3600;

        return floor($big_date);
    }
}

<?php

namespace Xls\Writer;

/**
 * Class for writing Excel BIFF records.
 *
 * From "MICROSOFT EXCEL BINARY FILE FORMAT" by Mark O'Brien (Microsoft Corporation):
 *
 * BIFF (BInary File Format) is the file format in which Excel documents are
 * saved on disk.  A BIFF file is a complete description of an Excel document.
 * BIFF files consist of sequences of variable-length records. There are many
 * different types of BIFF records.  For example, one record type describes a
 * formula entered into a cell; one describes the size and location of a
 * window into a document; another describes a picture format.
 *
 * @author   Xavier Noguer <xnoguer@php.net>
 * @category FileFormats
 * @package  Spreadsheet_Excel_Writer
 */

class BIFFwriter
{
    /**
     * BIFF5
     *
     * Microsoft Excel version 5.0 (XL5)
     * Microsoft Excel 95 (XL7) (also called Microsoft Excel version 7)
     */
    const VERSION_5 = 0x0500;

    /**
     * BIFF8
     *
     * Microsoft Excel 97 (XL8)
     * Microsoft Excel 2000 (XL9)
     * Microsoft Excel 2002 (XL10)
     * Microsoft Office Excel 2003 (XL11)
     * Microsoft Office Excel 2007 (XL12)
     */
    const VERSION_8 = 0x0600;

    const BYTE_ORDER_LE = 0;
    const BYTE_ORDER_BE = 1;

    /**
     * @var integer
     */
    public $BIFF_version = self::VERSION_5;

    /**
     * The byte order of this architecture. 0 => little endian, 1 => big endian
     * @var integer
     */
    public $byte_order;

    /**
     * The string containing the data of the BIFF stream
     * @var string
     */
    public $data;

    /**
     * The size of the data in bytes. Should be the same as strlen($this->data)
     * @var integer
     */
    public $datasize;

    /**
     * The maximun length for a BIFF record. See _addContinue()
     * @var integer
     * @see _addContinue()
     */
    public $limit;

    /**
     * The temporary dir for storing the OLE file
     * @var string
     */
    public $tmpDir;

    /**
     * The temporary file for storing the OLE file
     * @var string
     */
    public $tmpFile;

    /**
     * @param int $biffVersion
     *
     * @throws \Exception
     */
    public function __construct($biffVersion = self::VERSION_5)
    {
        $this->BIFF_version = $biffVersion;
        $this->data = '';
        $this->datasize = 0;
        $this->limit = 2080;
        $this->tmpDir = '';

        $this->setByteOrder();
    }

    /**
     * Determine the byte order and store it as class data to avoid
     * recalculating it for each call to new().
     *
     */
    protected function setByteOrder()
    {
        // Check if "pack" gives the required IEEE 64bit float
        $teststr = pack("d", 1.2345);
        $number = pack("C8", 0x8D, 0x97, 0x6E, 0x12, 0x83, 0xC0, 0xF3, 0x3F);
        if ($number == $teststr) {
            $byte_order = self::BYTE_ORDER_LE;
        } elseif ($number == strrev($teststr)) {
            $byte_order = self::BYTE_ORDER_BE;
        } else {
            // Give up. I'll fix this in a later version.
            throw new \Exception(
                "Required floating point format is not supported on this platform."
            );
        }
        $this->byte_order = $byte_order;
    }

    /**
     * General storage function
     *
     * @param string $data binary data to prepend
     * @access private
     */
    protected function prepend($data)
    {
        if (strlen($data) > $this->limit) {
            $data = $this->addContinue($data);
        }
        $this->data = $data . $this->data;
        $this->datasize += strlen($data);
    }

    /**
     * General storage function
     *
     * @param string $data binary data to append
     * @access private
     */
    protected function append($data)
    {
        if (strlen($data) > $this->limit) {
            $data = $this->addContinue($data);
        }
        $this->data = $this->data . $data;
        $this->datasize += strlen($data);
    }

    /**
     * Writes Excel BOF record to indicate the beginning of a stream or
     * sub-stream in the BIFF file.
     *
     * @param  integer $type Type of BIFF file to write: 0x0005 Workbook,
     *                       0x0010 Worksheet.
     * @throws \Exception
     */
    protected function storeBof($type)
    {
        $record = 0x0809; // Record identifier

        // According to the SDK $build and $year should be set to zero.
        // However, this throws a warning in Excel 5. So, use magic numbers.
        if ($this->BIFF_version == self::VERSION_5) {
            $length = 0x0008;
            $unknown = '';
            $build = 0x096C;
            $year = 0x07C9;
        } elseif ($this->BIFF_version == self::VERSION_8) {
            $length = 0x0010;
            $unknown = pack("VV", 0x00000041, 0x00000006); //unknown last 8 bytes for BIFF8
            $build = 0x0DBB;
            $year = 0x07CC;
        } else {
            throw new \Exception("Unknown BIFF version");
        }

        $header = pack("vv", $record, $length);
        $data = pack("vvvv", $this->BIFF_version, $type, $build, $year);
        $this->prepend($header . $data . $unknown);
    }

    /**
     * Writes Excel EOF record to indicate the end of a BIFF stream.
     *
     * @access private
     */
    protected function storeEof()
    {
        $record = 0x000A; // Record identifier
        $length = 0x0000; // Number of bytes to follow
        $header = pack("vv", $record, $length);
        $this->append($header);
    }

    /**
     * Excel limits the size of BIFF records. In Excel 5 the limit is 2084 bytes. In
     * Excel 97 the limit is 8228 bytes. Records that are longer than these limits
     * must be split up into CONTINUE blocks.
     *
     * This function takes a long BIFF record and inserts CONTINUE records as
     * necessary.
     *
     * @param  string $data The original binary data to be written
     * @return string        A very convenient string of continue blocks
     * @access private
     */
    protected function addContinue($data)
    {
        $limit = $this->limit;
        $record = 0x003C; // Record identifier

        // The first 2080/8224 bytes remain intact. However, we have to change
        // the length field of the record.
        $tmp = substr($data, 0, 2) . pack("v", $limit - 4) . substr($data, 4, $limit - 4);

        $header = pack("vv", $record, $limit); // Headers for continue records

        // Retrieve chunks of 2080/8224 bytes +4 for the header.
        $data_length = strlen($data);
        for ($i = $limit; $i < ($data_length - $limit); $i += $limit) {
            $tmp .= $header;
            $tmp .= substr($data, $i, $limit);
        }

        // Retrieve the last chunk of data
        $header = pack("vv", $record, strlen($data) - $i);
        $tmp .= $header;
        $tmp .= substr($data, $i, strlen($data) - $i);

        return $tmp;
    }

    /**
     * Sets the temp dir used for storing the OLE file
     *
     * @access public
     * @param string $dir The dir to be used as temp dir
     * @return true if given dir is valid, false otherwise
     */
    public function setTempDir($dir)
    {
        if (is_dir($dir)) {
            $this->tmpDir = $dir;
            return true;
        }

        return false;
    }

    /**
     * @param $version
     *
     * @return bool
     */
    public static function isVersionSupported($version)
    {
        return $version === self::VERSION_5 || $version === self::VERSION_8;
    }
}

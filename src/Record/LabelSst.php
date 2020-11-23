<?php
namespace Xls\Record;

class LabelSst extends AbstractRecord
{
    public const NAME = 'LABELSST';
    public const ID = 0x00FD;

    /**
     * @param int $row
     * @param int $col
     * @param int $strIdx
     * @param null $format
     *
     * @return string
     */
    public function getData($row, $col, $strIdx, $format = null)
    {
        $xf = $this->xf($format);

        $data = pack('vvvV', $row, $col, $xf, $strIdx);

        return $this->getFullRecord($data);
    }
}

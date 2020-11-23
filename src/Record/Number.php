<?php
namespace Xls\Record;

class Number extends AbstractRecord
{
    public const NAME = 'NUMBER';
    public const ID = 0x0203;

    /**
     * @param int $row
     * @param int $col
     * @param float $num
     * @param null $format
     *
     * @return string
     */
    public function getData($row, $col, $num, $format = null)
    {
        $xf = $this->xf($format);
        $data = pack("vvv", $row, $col, $xf);
        $data .= pack("d", $num);

        return $this->getFullRecord($data);
    }
}

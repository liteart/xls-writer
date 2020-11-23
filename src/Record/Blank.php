<?php
namespace Xls\Record;

class Blank extends AbstractRecord
{
    public const NAME = 'BLANK';
    public const ID = 0x0201;

    /**
     * @param $row
     * @param $col
     * @param $format
     *
     * @return string
     */
    public function getData($row, $col, $format)
    {
        $xf = $this->xf($format);
        $data = pack("vvv", $row, $col, $xf);

        return $this->getFullRecord($data);
    }
}

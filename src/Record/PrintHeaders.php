<?php
namespace Xls\Record;

class PrintHeaders extends AbstractRecord
{
    public const NAME = 'PRINTHEADERS';
    public const ID = 0x2A;

    /**
     * @param $printRowColHeaders
     *
     * @return string
     */
    public function getData($printRowColHeaders)
    {
        $data = pack("v", intval($printRowColHeaders));

        return $this->getFullRecord($data);
    }
}

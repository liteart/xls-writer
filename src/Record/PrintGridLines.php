<?php
namespace Xls\Record;

class PrintGridLines extends AbstractRecord
{
    public const NAME = 'PRINTGRIDLINES';
    public const ID = 0x2B;

    /**
     * @param $printGridLines
     *
     * @return string
     */
    public function getData($printGridLines)
    {
        $data = pack("v", intval($printGridLines));

        return $this->getFullRecord($data);
    }
}

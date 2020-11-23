<?php
namespace Xls\Record;

class Gridset extends AbstractRecord
{
    public const NAME = 'GRIDSET';
    public const ID = 0x82;

    /**
     * @param $gridsetVisible
     *
     * @return string
     */
    public function getData($gridsetVisible)
    {
        $data = pack("v", intval($gridsetVisible));

        return $this->getFullRecord($data);
    }
}

<?php

namespace Xls\Record;

class Hcenter extends AbstractRecord
{
    public const NAME = 'HCENTER';
    public const ID = 0x0083;

    /**
     * @param $centering
     *
     * @return string
     */
    public function getData($centering)
    {
        $data = pack('v', $centering);

        return $this->getFullRecord($data);
    }
}

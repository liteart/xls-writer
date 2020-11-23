<?php

namespace Xls\Record;

class ExternalBook extends AbstractRecord
{
    public const NAME = 'EXTERNALBOOK';
    public const ID = 0x01AE;

    /**
     * Generate Internal SUPBOOK record
     * @param int $worksheetsCount
     *
     * @return string
     */
    public function getData($worksheetsCount)
    {
        $data = pack("vv", $worksheetsCount, 0x0401);

        return $this->getFullRecord($data);
    }
}

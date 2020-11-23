<?php

namespace Xls\Record;

class Protect extends AbstractRecord
{
    public const NAME = 'PROTECT';
    public const ID = 0x0012;

    /**
     * Generate the PROTECT biff record
     *
     * @param int $lock
     *
     * @return string
     */
    public function getData($lock)
    {
        $data = pack("v", $lock);

        return $this->getFullRecord($data);
    }
}

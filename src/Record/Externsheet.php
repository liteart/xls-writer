<?php

namespace Xls\Record;

class Externsheet extends AbstractRecord
{
    public const NAME = 'EXTERNSHEET';
    public const ID = 0x0017;

    /**
     * @param $refs
     *
     * @return string
     */
    public function getData($refs)
    {
        $refCount = count($refs);
        $data = pack('v', $refCount);

        foreach ($refs as $ref) {
            $data .= $ref;
        }

        return $this->getFullRecord($data);
    }
}

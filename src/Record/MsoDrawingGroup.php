<?php

namespace Xls\Record;

class MsoDrawingGroup extends AbstractRecord
{
    public const NAME = 'MSODRAWINGGROUP';
    public const ID = 0x00EB;

    public function getData($hexStrData)
    {
        $data = pack('H*', str_replace(' ', '', $hexStrData));

        return $this->getFullRecord($data);
    }
}

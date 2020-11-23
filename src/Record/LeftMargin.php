<?php

namespace Xls\Record;

class LeftMargin extends AbstractRecord
{
    public const NAME = 'LEFTMARGIN';
    public const ID = 0x26;

    /**
     * @param $margin
     *
     * @return string
     */
    public function getData($margin)
    {
        $data = pack("d", $margin);

        return $this->getFullRecord($data);
    }
}

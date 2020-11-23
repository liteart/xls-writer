<?php
namespace Xls\Record;

class Codepage extends AbstractRecord
{
    public const NAME = 'CODEPAGE';
    public const ID = 0x0042;

    /**
     * Generate the CODEPAGE biff record
     * @param $codepage
     *
     * @return string
     */
    public function getData($codepage)
    {
        $data = pack('v', $codepage);

        return $this->getFullRecord($data);
    }
}

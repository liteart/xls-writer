<?php

namespace Xls\Record;

class ObjComment extends Obj
{
    const TYPE = 0x19;

    /**
     * @param int $objId
     * @param string $guid comment guid (only for tests)
     *
     * @return string
     */
    public function getData($objId, $guid = null)
    {
        $data = $this->getFtCmoSubrecord($objId);
        $data .= $this->getFtNtsSubrecord($guid);
        $data .= $this->getFtEndSubrecord();

        return $this->getFullRecord($data);
    }
}

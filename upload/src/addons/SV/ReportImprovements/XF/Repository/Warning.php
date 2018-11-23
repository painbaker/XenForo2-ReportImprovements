<?php

namespace SV\ReportImprovements\XF\Repository;

use XF\Entity\ThreadReplyBan;

/**
 * Class Warning
 * 
 * Extends \XF\Repository\Warning
 *
 * @package SV\ReportImprovements\XF\Repository
 */
class Warning extends XFCP_Warning
{
    /**
     * @param \XF\Entity\Warning $warning
     * @param string $type
     */
    public function logOperation(\XF\Entity\Warning $warning, $type)
    {
        /** @var \SV\ReportImprovements\Service\WarningLog\Creator $warningLogCreator */
        $warningLogCreator = $this->app()->service('SV\ReportImprovements:WarningLog\Creator', $warning, $type);
        if ($warningLogCreator->validate($errors))
        {
            $warningLogCreator->save();
        }
    }
}
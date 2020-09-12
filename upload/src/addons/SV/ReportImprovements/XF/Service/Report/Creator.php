<?php

namespace SV\ReportImprovements\XF\Service\Report;

use SV\ReportImprovements\XF\Entity\Report;
use SV\ReportImprovements\XF\Entity\ReportComment;

/**
 * Extends \XF\Service\Report\Creator
 *
 * @property Report        $report
 * @property ReportComment $comment
 */
class Creator extends XFCP_Creator
{
    protected function setDefaults()
    {
        $applyXFWorkAround = $this->report->report_state === 'open';
        parent::setDefaults();
        if ($applyXFWorkAround && $this->comment->state_change === 'open')
        {
            $this->comment->state_change = '';
        }

        $this->report->last_modified_id = $this->comment->getDeferredId();
        $this->report->hydrateRelation('LastModified', $this);
    }

    /**
     * @return \XF\Entity\Report
     */
    public function getReport()
    {
        return $this->report;
    }

    /**
     * @return \XF\Entity\ReportComment
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @throws \Exception
     */
    public function sendNotifications()
    {
        parent::sendNotifications();

        if (!$this->report->exists() ||
            !$this->comment->exists())
        {
            return;
        }

        /** @var \SV\ReportImprovements\XF\Repository\Report $reportRepo */
        $reportRepo = $this->repository('XF:Report');
        $userIdsToAlert = $reportRepo->findUserIdsToAlertForSvReportImprov($this->report);

        /** @var Notifier $notifier */
        $notifier = $this->service('XF:Report\Notifier', $this->report, $this->comment);
        $notifier->setCommentersUserIds($userIdsToAlert);
        $notifier->notify();
    }
}
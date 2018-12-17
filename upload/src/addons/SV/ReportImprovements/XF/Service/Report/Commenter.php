<?php

namespace SV\ReportImprovements\XF\Service\Report;

use SV\ReportImprovements\XF\Entity\Report;
use SV\ReportImprovements\XF\Entity\ReportComment;

/**
 * Class Commenter
 *
 * Extends \XF\Service\Report\Commenter
 *
 * @package SV\ReportImprovements\XF\Service\Report
 *
 * @property Report $report
 * @property ReportComment $comment
 */
class Commenter extends XFCP_Commenter
{
    public function setReportState($newState = null, \XF\Entity\User $assignedUser = null)
    {
        $oldAssignedUserId = null;
        if ($newState !== 'open')
        {
            $oldAssignedUserId = $this->report->assigned_user_id ;
        }

        parent::setReportState($newState, $assignedUser);

        if ($oldAssignedUserId !== null && $this->report->assigned_user_id === 0)
        {
            $oldState = $this->report->getExistingValue('report_state');
            if ($newState && ($newState != $oldState || $this->report->isChanged('assigned_user_id')))
            {
                $this->comment->state_change = '';
            }
            $this->report->assigned_user_id = $oldAssignedUserId;
        }
    }

    protected function finalSetup()
    {
        $sendAlert = $this->sendAlert;
        $this->sendAlert = false;

        if ($sendAlert)
        {
            $this->comment->alertSent = true;
            $this->comment->alertComment = $this->alertComment;
        }

        parent::finalSetup();

        $this->sendAlert = $sendAlert;
    }

    /**
     * @throws \Exception
     */
    public function sendNotifications()
    {
        parent::sendNotifications();

        $comment = $this->comment;

        /** @var \SV\ReportImprovements\XF\Repository\Report $reportRepo */
        $reportRepo = $this->repository('XF:Report');
        $userIdsToAlert = $reportRepo->findUserIdsToAlertForSvReportImprov($comment);

        /** @var \SV\ReportImprovements\XF\Service\Report\Notifier $notifier */
        $notifier = $this->service('XF:Report\Notifier', $this->report, $comment);
        $notifier->setCommentersUserIds($userIdsToAlert);
        $notifier->notify();
    }
}
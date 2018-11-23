<?php

namespace SV\ReportImprovements\Service\WarningLog;

use SV\ReportImprovements\Entity\WarningLog;
use SV\ReportImprovements\Globals;
use XF\Entity\ThreadReplyBan;
use XF\Entity\Warning;
use XF\Mvc\Entity\Entity;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

/**
 * Class Creator
 *
 * @package SV\ReportImprovements\XF\Service\WarningLog
 */
class Creator extends AbstractService
{
    use ValidateAndSavableTrait;

    /**
     * @var Warning|\SV\ReportImprovements\XF\Entity\Warning
     */
    protected $warning;

    /**
     * @var ThreadReplyBan|\SV\ReportImprovements\XF\Entity\ThreadReplyBan
     */
    protected $threadReplyBan;

    /**
     * @var string
     */
    protected $operationType;

    /**
     * @var WarningLog
     */
    protected $warningLog;

    /**
     * @var \SV\ReportImprovements\XF\Service\Report\Creator
     */
    protected $reportCreator;

    /**
     * @var \SV\ReportImprovements\XF\Service\Report\Commenter
     */
    protected $reportCommenter;

    /**
     * Creator constructor.
     *
     * @param \XF\App $app
     * @param Entity  $content
     * @param         $operationType
     *
     * @throws \Exception
     */
    public function __construct(\XF\App $app, Entity $content, $operationType)
    {
        parent::__construct($app);

        if ($content instanceof Warning)
        {
            $this->warning = $content;
        }
        else if ($content instanceof ThreadReplyBan)
        {
            $this->threadReplyBan = $content;
        }
        else
        {
            throw new \LogicException('Unsupported content type provided.');
        }

        $this->operationType = $operationType;
        $this->setupDefaults();
    }

    /**
     * @throws \Exception
     */
    protected function setupDefaults()
    {
        $this->warningLog = $this->em()->create('SV\ReportImprovements:WarningLog');

        $this->warningLog->operation_type = $this->operationType;
        if ($this->warningLog->operation_type === 'new')
        {
            $this->warningLog->warning_edit_date = 0;
        }
        else
        {
            $this->warningLog->warning_edit_date = \XF::$time;
        }

        if ($this->warning)
        {
            $fieldsToCopy = [
                'content_type',
                'content_id',
                'content_title',
                'user_id',
                'warning_id',
                'warning_date',
                'warning_user_id',
                'warning_definition_id',
                'title',
                'notes',
                'points',
                'expiry_date',
                'is_expired',
                'extra_user_group_ids'
            ];

            foreach ($fieldsToCopy AS $field)
            {
                $fieldValue = $this->warning->get($field);
                $this->warningLog->set($field, $fieldValue);
            }

            $reportMessage = $this->warning->title;
            if (!empty($this->warning->notes))
            {
                $reportMessage .= "\r\n" . $this->warning->notes;
            }

            \XF::asVisitor($this->warning->WarnedBy, function () use($reportMessage)
            {
                if (!$this->warning->Report && $this->app->options()->sv_report_new_warnings)
                {
                    $this->reportCreator = $this->service('XF:Report\Creator', $this->warning->content_type, $this->warning->Content);
                    $this->reportCreator->setMessage($reportMessage);
                }
                else if ($this->warning->Report)
                {
                    $this->reportCommenter = $this->service('XF:Report\Commenter', $this->warning->Report);
                    $this->reportCommenter->setMessage($reportMessage);
                    if ($this->warningLog->operation_type === 'new')
                    {
                        $this->reportCommenter->setReportState('resolved');
                    }
                }
            });
        }
        else if ($this->threadReplyBan)
        {
            $this->warningLog->warning_date = \XF::$time;
            $this->warningLog->content_type = 'user';
            $this->warningLog->content_id = $this->threadReplyBan->user_id;
            $this->warningLog->content_title = $this->threadReplyBan->User->username;
            $this->warningLog->user_id = $this->threadReplyBan->user_id;
            $this->warningLog->warning_user_id = \XF::visitor()->user_id;
            $this->warningLog->warning_definition_id = null;
            $this->warningLog->title = \XF::phrase('svReportImprov_reply_banned')->render();
            $this->warningLog->notes = $this->threadReplyBan->reason;

            \XF::asVisitor($this->threadReplyBan->User, function ()
            {
                if (!$this->threadReplyBan->Report)
                {
                    $this->reportCreator = $this->service('XF:Report\Creator', 'user', $this->threadReplyBan->User);
                    $this->reportCreator->setMessage($this->threadReplyBan->reason);
                }
                else if ($this->threadReplyBan->Report)
                {
                    $this->reportCommenter = $this->service('XF:Report\Commenter', $this->threadReplyBan->Report);
                    $this->reportCommenter->setMessage($this->threadReplyBan->reason);
                    if ($this->warningLog->operation_type === 'new')
                    {
                        $this->reportCommenter->setReportState('resolved');
                    }
                }
            });
        }
    }

    /**
     * @return array
     * @throws \Exception
     */
    protected function _validate()
    {
        $this->warningLog->preSave();
        $warningLogErrors = $this->warningLog->getErrors();
        $reportCreatorErrors = [];
        $reportCommenterErrors = [];
        $asUser = null;

        if ($this->warning)
        {
            $asUser = $this->warning->User;
        }
        else if ($this->threadReplyBan)
        {
            $asUser = $this->threadReplyBan->User;
        }
        else
        {
            throw new \LogicException('User not available for warning or thread reply ban.');
        }

        \XF::asVisitor($asUser, function ()
        {
            if ($this->reportCreator)
            {
                $this->reportCreator->validate($reportCreatorErrors);
            }
            else
            {
                $this->reportCommenter->validate($reportCommenterErrors);
            }
        });

        $errors = array_merge($warningLogErrors, $reportCreatorErrors, $reportCommenterErrors);
        foreach ($errors AS $error)
        {
            \XF::dumpSimple($error->render('raw'));
        }

        return array_merge($warningLogErrors, $reportCreatorErrors, $reportCommenterErrors);
    }

    /**
     * @return WarningLog
     * @throws \XF\PrintableException
     * @throws \Exception
     */
    protected function _save()
    {
        $this->warningLog->save();

        $asUser = null;

        if ($this->warning)
        {
            $asUser = $this->warning->User;
        }
        else if ($this->threadReplyBan)
        {
            $asUser = $this->threadReplyBan->User;
        }
        else
        {
            throw new \LogicException('User not available for warning or thread reply ban.');
        }

        \XF::asVisitor($asUser, function ()
        {
            if ($this->reportCreator)
            {
                /** @var \SV\ReportImprovements\XF\Entity\Report $report */
                if ($report = $this->reportCreator->save())
                {
                    $report->report_state = 'resolved';

                    if ($report->LastModified)
                    {
                        $report->LastModified->state_change = '';
                    }

                    $report->save();
                }
            }
            else if ($this->reportCommenter)
            {
                /** @var \SV\ReportImprovements\XF\Entity\ReportComment $reportComment */
                if ($reportComment = $this->reportCommenter->save())
                {
                    $reportComment->state_change = '';
                    $reportComment->delete();
                }
            }
        });

        return $this->warningLog;
    }
}
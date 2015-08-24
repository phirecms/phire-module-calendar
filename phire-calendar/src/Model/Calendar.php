<?php

namespace Phire\Calendar\Model;

use Phire\Content\Table;
use Phire\Model\AbstractModel;
use Pop\View\View;

class Calendar extends AbstractModel
{

    /**
     * Get calendar by content type ID
     *
     * @param  int     $tid
     * @param  boolean $time
     * @return View
     */
    public function getById($tid, $time = false)
    {
        $calendar = new View(__DIR__ . '/../../view/calendar.phtml');

        $sql = Table\Content::sql();
        $sql->select([
            'id'        => DB_PREFIX . 'content.id',
            'type_id'   => DB_PREFIX . 'content.type_id',
            'title'     => DB_PREFIX . 'content.title',
            'uri'       => DB_PREFIX . 'content.uri',
            'slug'      => DB_PREFIX . 'content.slug',
            'status'    => DB_PREFIX . 'content.status',
            'roles'     => DB_PREFIX . 'content.roles',
            'publish'   => DB_PREFIX . 'content.publish',
            'expire'    => DB_PREFIX . 'content.expire'
        ]);

        // YYYY-MM
        if ((null !== $this->date) && (strlen($this->date) == 7) && (strpos($this->date, '-') !== false)) {
            $dateAry = explode('-', $this->date);
            $start = $dateAry[0] . '-' . $dateAry[1] . '-01 00:00:00';
            $end   = $dateAry[0] . '-' . $dateAry[1] . '-' .
                date('t', strtotime($dateAry[0] . '-' . $dateAry[1] . '-01')) . ' 23:59:59';
        } else {
            $y          = date('Y');
            $m          = date('m');
            $this->date = $y . '-' . $m;
            $start      = $y . '-' . $m . '-01 00:00:00';
            $end        = $y . '-' . $m . '-' .
                date('t', strtotime($y . '-' . $m . '-01')) . ' 23:59:59';
        }

        $sql->select()
            ->where('type_id = :type_id')
            ->where('status = :status')
            ->where('publish >= :publish1')
            ->where('publish <= :publish2');

        $sql->select()->orderBy('publish', 'ASC');

        $params = [
            'type_id' => $tid,
            'status'  => 1,
            'publish' => [
                $start,
                $end
            ]
        ];

        $calendar->date         = $this->date;
        $calendar->time         = $time;
        $calendar->weekdays     = $this->weekdays;
        $calendar->numOfWeeks   = $this->getNumberOfWeeks();
        $calendar->monthOptions = $this->getMonthOptions();
        $calendar->startDay     = date('D', strtotime($this->date));
        $calendar->numOfDays    = date('t', strtotime($this->date));

        $content = Table\Content::execute((string)$sql, $params)->rows();
        $events  = [];

        foreach ($content as $c) {
            $day = substr($c->publish, 0, strpos($c->publish, ' '));
            if (!isset($events[$day])) {
                $events[$day] = [];
            }

            $roles = unserialize($c->roles);
            if ((count($roles) == 0) || ((null !== $this->user_role_id) && in_array($this->user_role_id, $roles))) {
                $events[$day][] = $c;
            }

            if (null !== $c->expire) {
                $start     = (int)substr($day, (strrpos($day, '-') + 1)) + 1;
                $expireDay = substr($c->expire, 0, strpos($c->expire, ' '));
                $end       = (int)substr($expireDay, (strrpos($expireDay, '-') + 1));
                for ($i = $start; $i <= $end; $i++) {
                    if ($i <= $calendar->numOfDays) {
                        $expDay = $calendar->date . '-' . ((strlen($i) == 1) ? '0' . $i : $i);
                        if (!isset($events[$expDay])) {
                            $events[$expDay] = [];
                        }
                        if ((count($roles) == 0) || ((null !== $this->user_role_id) && in_array($this->user_role_id, $roles))) {
                            $events[$expDay][] = $c;
                        }
                    }
                }
            }

        }

        $calendar->events = $events;

        return $calendar;
    }

    /**
     * Get number of weeks
     *
     * @return int
     */
    protected function getNumberOfWeeks()
    {
        $first = date("w", strtotime($this->date));
        $last  = date("t", strtotime($this->date));
        return (1 + ceil(($last - 7 + $first) / 7));
    }

    /**
     * Get month of options
     *
     * @return array
     */
    protected function getMonthOptions()
    {
        $options      = [];
        $currentMonth = date('n');
        $currentYear  = date('Y');
        $rangeFormat  = (null !== $this->range_format) ? $this->range_format : 'M Y';

        if ((null !== $this->range) && (strpos($this->range, '-') !== false)) {
            $rangeAry = explode('-', $this->range);
            if (($rangeAry[0] == 'SOY') && ($rangeAry[1] == 'EOY')) {
                $currentMonth = 1;
                $range        = 12;
            } else if (is_numeric($rangeAry[0]) && is_numeric($rangeAry[1])) {
                if ($rangeAry[0] > 13) {
                    $rangeAry[0] = 13;
                }
                if ($rangeAry[1] >= 12) {
                    $rangeAry[1] = 13;
                }
                $range = $rangeAry[0] + $rangeAry[1];
                if (($currentMonth - $rangeAry[0]) < 0) {
                    $currentMonth = 12 + ($currentMonth - $rangeAry[0]);
                    $currentYear--;
                } else {
                    $currentMonth = $currentMonth - $rangeAry[0];
                }
            } else {
                $range = 12;
            }
        } else {
            switch ($this->range) {
                case (null):
                    $range = 12;
                    break;
                case ('EOY'):
                    $range = 12 - date('m') + 1;
                    break;
                default:
                    $range = $this->range;
            }
        }

        for ($i = 0; $i < $range; $i++) {
            $value = $currentYear . '-' . (($currentMonth < 10) ? '0' . $currentMonth : $currentMonth);
            $options[$value] = date($rangeFormat, strtotime($value));
            if ($currentMonth == 12) {
                $currentMonth = 1;
                $currentYear++;
            } else {
                $currentMonth++;
            }
        }

        return $options;
    }

}
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
     * @param  int    $tid
     * @return View
     */
    public function getById($tid)
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
            'publish'   => DB_PREFIX . 'content.publish'
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

        $params = [
            'type_id' => $tid,
            'status'  => 1,
            'publish' => [
                $start,
                $end
            ]
        ];

        $calendar->date       = $this->date;
        $calendar->weekdays   = $this->weekdays;
        $calendar->numOfWeeks = $this->getNumberOfWeeks();
        $calendar->startDay   = date('D', strtotime($this->date));
        $calendar->numOfDays  = date('t', strtotime($this->date));
        //$calendar->events   = Table\Content::execute((string)$sql, $params)->rows();

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

}
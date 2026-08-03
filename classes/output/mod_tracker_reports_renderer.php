<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    mod_tracker
 * @category   mod
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

class mod_tracker_reports_renderer extends \plugin_renderer_base {

    protected $tracker;
    protected $context;
    protected $statuscodes;
    protected $statuskeys;
    protected $dateiter; // Iterator.
    protected $ticketsbymonth;
    protected $ticketsprogressbymonth;
    protected $lowest;
    protected $highest;

    // N2NCU 2026-08-02: $colwidth was the one property this class used without
    // declaring. Assigned in init() and read by six of the table renderers
    // below, so PHP created it dynamically - "Creation of dynamic property
    // mod_tracker_reports_renderer::$colwidth is deprecated" on every load of
    // view.php?view=reports.
    //
    // Deprecated in PHP 8.2 and REMOVED in 9, so this is a future fatal on a
    // page that works today, not a cosmetic notice. This branch runs on 8.3.
    //
    // Found by tools/smoke-crawl.sh in moodle-core-patches - the first
    // deprecation any crawl has surfaced, and only reachable as an admin.
    protected $colwidth;

    // N2NCU 2026-08-03: four more, found the same way $colwidth was and missed
    // the first time round.
    //
    // The check that cleared this class looked for `$this->x =` assignments.
    // These are assigned as ARRAY ELEMENTS - `$this->totalsum[$current] = ...`
    // in progress_trends() - which creates the property just the same but does
    // not match that pattern. The right test is every `$this->x` that is not
    // followed by an opening bracket, compared against the declarations.
    //
    // They only surface on view=reports&screen=evolution. The default screen is
    // status, which does not call progress_trends(), so neither the crawler nor
    // a casual look at the reports page ever reached them.
    protected $totalsum;
    protected $trendsum;
    protected $ressum;
    protected $testsum;

    public function init($tracker) {

        $this->tracker = $tracker;
        $cm = get_coursemodule_from_instance('tracker', $tracker->id);
        $this->context = context_module::instance($cm->id);

        $this->statuskeys = tracker_get_statuskeys($tracker);
        $this->statuscodes = tracker_get_statuscodes($tracker);

        $this->ticketsbymonth = tracker_get_stats_by_month($tracker);
        $this->ticketsprogressbymonth = tracker_backtrack_stats_by_month($tracker);

        if (!empty($this->ticketsbymonth)) {
            $ticketdates = $this->ticketsbymonth;
            unset($ticketdates['sum']);
            $availdates = array_keys($ticketdates);
            if (!empty($availdates)) {
                $this->lowest = $availdates[0];

                $this->highest = $availdates[count($availdates) - 1];
                $low = new \StdClass();
                list($low->year, $low->month) = explode('-', $this->lowest);
            }

            $this->dateiter = new date_iterator($low->year, $low->month);
            $this->colwidth = 60 / $this->dateiter->getiterations($this->highest);
        }
    }

    public function counters($alltickets) {

        $str = '<div class="container-fluid">';
        $str .= '<div class="row-fluid">';
        $str .= '<div class="span12 col-12">';
        $str .= $this->output->heading(get_string('countbymonth', 'tracker', $alltickets), 3);

        $str .= $this->count_by_month(true, $this->ticketsbymonth);

        $str .= $this->count_by_month(false, $this->ticketsbymonth);

        $str .= $this->count_new();

        $str .= '</div>';
        $str .= '</div>';
        $str .= '</div>';

        return $str;
    }

    public function evolution($alltickets) {

        $str = '<table width="100%" cellpadding="5">';
        $str .= '<tr valign="top">';
        $str .= '<td>';
        $str .= $this->output->heading(get_string('evolutionbymonth', 'tracker', $alltickets), 3);
        $str .= $this->count_by_month(false, $this->ticketsprogressbymonth);
        $str .= $this->count_by_month(true, $this->ticketsprogressbymonth);

        $str .= $this->progress_trends();

        // N2NCU 2026-08-03: was local_vflibs_jqplot_print_graph(). Moodle has
        // shipped a charting API since 3.2, so the chart is now built with
        // \core\chart_line and rendered by core.
        //
        // The point is not the chart. local_vflibs is 214MB - 68MB of it Windows
        // .exe and .dll under xpdf - and mod_tracker was its only consumer on
        // this site, through exactly three lines: this call, the
        // local_vflibs_require_jqplot_libs() in view.php, and the dependency in
        // version.php. With all three gone the plugin uninstalls entirely,
        // without trimming a single vendored file (see TECH_DEBT 6).
        //
        // Same three series, same colours, same axis labels. Core renders via
        // Chart.js rather than jqplot, so it will not look identical.
        $labels = array();
        $active = array();
        $intest = array();
        $resolved = array();
        foreach ($this->totalsum as $k => $v) {
            $labels[] = $k;
            $active[] = (int)$this->trendsum[$k];
            $intest[] = (int)$this->testsum[$k];
            $resolved[] = (int)$this->ressum[$k];
        }

        $chart = new \core\chart_line();
        $chart->set_title(get_string('generaltrend', 'tracker'));
        $chart->set_labels($labels);

        $activeseries = new \core\chart_series(get_string('activeplural', 'tracker'), $active);
        $activeseries->set_color('#C00000');
        $chart->add_series($activeseries);

        $intestseries = new \core\chart_series(get_string('intest', 'tracker'), $intest);
        $intestseries->set_color('#80FF80');
        $chart->add_series($intestseries);

        $resolvedseries = new \core\chart_series(get_string('resolvedplural2', 'tracker'), $resolved);
        $resolvedseries->set_color('#00C000');
        $chart->add_series($resolvedseries);

        $chart->get_xaxis(0, true)->set_label(get_string('month', 'tracker'));
        $yaxis = $chart->get_yaxis(0, true);
        $yaxis->set_label(get_string('tickets', 'tracker'));
        $yaxis->set_stepsize(1);

        // Appended rather than echoed. jqplot printed straight to output, which
        // is why the graph appeared even though everything else here did not -
        // see the return below.
        $str .= $this->output->render_chart($chart, false);

        $str .= '</td>';
        $str .= '</tr>';
        $str .= '</table>';

        // N2NCU 2026-08-03: this function never returned $str. report/evolution.php
        // does `echo $renderer->evolution($alltickets);`, so it echoed null - the
        // heading, both count_by_month tables and progress_trends() were built and
        // silently discarded on every load. Only the graph showed, because the
        // jqplot call echoed directly rather than appending.
        //
        // So fixing this makes content appear that has never been visible. That is
        // the intended output of the function, but it IS a change worth looking at
        // rather than assuming.
        return $str;
    }

    public function count_by_month($isactive, $tickets) {

        if ($isactive) {
            $colorclass = 'red';
        } else {
            $colorclass = 'green';
        }

        $str = '';

        $str .= '<table width="95%" class="generaltable">';
        $str .= '<tr valign="top">';
        $str .= '<td width="40%" align="left">'.get_string('status', 'tracker').'</td>';
        $current = $this->dateiter->current();
        while (strcmp($current, $this->highest) <= 0) {
            $str .= '<td align="right" width="'.$this->colwidth.'%">'.$current.'</td>';
            $this->dateiter->next();
            $current = $this->dateiter->current();
        }
        $str .= '</tr>';

        foreach (array_keys($this->statuskeys) as $key) {
            if ($isactive) {
                $exclude = in_array($key, array(ABANDONNED, RESOLVED, VALIDATED, TRANSFERED));
            } else {
                $exclude = !in_array($key, array(ABANDONNED, RESOLVED, VALIDATED, TRANSFERED));
            }
            if ($exclude) {
                continue;
            }

            $str .= '<tr valign="top">';
            $str .= '<td width="40%" align="left" class="status-'.$this->statuscodes[$key].'">'.$this->statuskeys[$key].'</td>';
            $this->dateiter->reset();
            $current = $this->dateiter->current();
            $last = 0;
            while (strcmp($current, $this->highest) <= 0) {
                $str .= '<td align="right" width="'.$this->colwidth.'%">';
                $new = 0 + @$tickets[$current][$key];
                $diff = $new - $last;
                $valueclass = ($new == 0) ? 'nullclass' : '';
                $str .= '<span class="'.$valueclass.'">'.$new.'</span>';
                $str .= ' ';
                $str .= ($diff > 0) ? '<span class="'.$colorclass.'">(+'.$diff.')</span>' : '';
                $last = $new;
                $str .= '</td>';
                $this->dateiter->next();
                $current = $this->dateiter->current();
            }
            $str .= '</tr>';
        }
        $str .= '</table>';

        return $str;
    }

    public function count_new() {

        $str = '';

        $str .= '<table width="95%" class="generaltable">';
        $str .= '<tr valign="top">';
        $statusstr = get_string('createdinmonth', 'tracker', $this->ticketsbymonth['sum']);
        $str .= '<td width="40%" align="left" class="status-">'.$statusstr.'</td>';
        $this->dateiter->reset();
        $current = $this->dateiter->current();
        while (strcmp($current, $this->highest) <= 0) {
            $str .= '<td align="right" width="'.$this->colwidth.'%" class="c0 header"><b>';
            $new = 0 + @$ticketsbymonth[$current]['sum'];
            $valueclass = ($new == 0) ? 'nullclass' : '';
            $str .= '<span class="'.$valueclass.'">'.$new.'</span>';
            $str .= '</b>';
            $str .= '</td>';
            $this->dateiter->next();
            $current = $this->dateiter->current();
        }
        $str .= '</tr>';
        $str .= '</table>';

        return $str;
    }

    public function progress_trends() {

        $str = '<table width="95%" class="generaltable">';
        $str .= '<tr valign="top">';
        $str .= '<td width="40%" align="left" class="status-">'.get_string('runninginmonth', 'tracker').'</td>';

        $this->dateiter->reset();
        $current = $this->dateiter->current();
        while (strcmp($current, $this->highest) <= 0) {

            $str .= '<td align="right" width="'.$this->colwidth.'%" class="c0 header"><b>';

            $new = 0 + @$ticketsprogressbymonth[$current]['sum'];
            $this->totalsum[$current] = @$ticketsprogressbymonth[$current]['sum'];
            $this->trendsum[$current] = @$ticketsprogressbymonth[$current]['sum'] - @$ticketsprogressbymonth[$current][ABANDONNED];
            $valueclass = ($new == 0) ? 'nullclass' : '';
            $str .= '<span class="'.$valueclass.'">'.$new.'</span>';
            $str .= '</b>';
            $str .= '</td>';

            $this->dateiter->next();
            $current = $this->dateiter->current();
        }

        $str .= '</tr>';
        $str .= '<tr valign="top">';
        $str .= '<td width="40%" align="left" class="status-">'.get_string('inworkinmonth', 'tracker').'</td>';

        $this->dateiter->reset();
        $current = $this->dateiter->current();
        while (strcmp($current, $this->highest) <= 0) {

            $str .= '<td align="right" width="'.$this->colwidth.'%" class="c0 header">';

            $new = 0 + @$ticketsprogressbymonth[$current]['sumunres'];
            $this->ressum[$current] = @$ticketsprogressbymonth[$current][RESOLVED] + @$ticketsprogressbymonth[$current][ABANDONNED];
            $this->testsum[$current] = @$ticketsprogressbymonth[$current][RESOLVED] + @$ticketsprogressbymonth[$current][TESTING];
            $this->testsum[$current] += @$ticketsprogressbymonth[$current][ABANDONNED];
            $valueclass = ($new == 0) ? 'nullclass' : 'redtext';
            $str .= '<span class="'.$valueclass.'">'.$new.'</span>';

            $str .= '</td>';

            $this->dateiter->next();
            $current = $this->dateiter->current();
        }

        $str .= '</tr>';
        $str .= '<tr valign="top">';
        $str .= '<td width="40%" align="left" class="status-">'.get_string('elucidationratio', 'tracker').'</td>';

        $this->dateiter->reset();
        $current = $this->dateiter->current();
        while (strcmp($current, $this->highest) <= 0) {

            $str .= '<td align="right" width="'.$this->colwidth.'%" class="c0 header">';
            $realtickets = @$ticketsprogressbymonth[$current]['sum'] - @$ticketsprogressbymonth[$current][ABANDONNED];
            $cond = $realtickets != 0;
            $new = 0 + ($cond) ? (($realtickets - @$ticketsprogressbymonth[$current]['sumunres']) / $realtickets * 100) : 0;
            $valueclass = ($new == 0) ? 'nullclass' : '';
            $str .= '<span class="'.$valueclass.'">'.sprintf('%.1f', $new).'%</span>';
            $str .= '</td>';
            $this->dateiter->next();
            $current = $this->dateiter->current();
        }
        $str .= '</tr>';
        $str .= '</table>';

        return $str;
    }

    public function status_stats() {
        global $DB;

        $tickets = tracker_get_stats($this->tracker);
        $alltickets = $DB->count_records('tracker_issue', array('trackerid' => $this->tracker->id));

        $statsbyassignee = tracker_get_stats_by_user($this->tracker, 'assignedto');
        $statsbyreporter = tracker_get_stats_by_user($this->tracker, 'reportedby');

        $str = '';

        $str .= '<div class="container-fluid">'; // Table.
        $str .= '<div class="row-fluid">'; // Row.
        $str .= '<div class="span4 col-4">'; // Cell.

        $str .= $this->output->heading(get_string('countbystate', 'tracker', $alltickets), 3);
        $str .= $this->count_by_states(true, $tickets, $alltickets);
        $str .= $this->count_by_states(false, $tickets, $alltickets);

        $str .= '</div>'; // Cell.
        $str .= '<div class="span4 col-4">'; // Cell.

        $str .= $this->output->heading(get_string('countbyassignee', 'tracker', $alltickets), 3);
        $str .= $this->count_by_assignee($statsbyassignee, $alltickets);

        $str .= '</div>'; // Cell.
        $str .= '<div class="span4 col-4">'; // Cell.

        $str .= $this->output->heading(get_string('countbyreporter', 'tracker', $alltickets), 3);
        $str .= $this->count_by_reporter($statsbyreporter, $alltickets);

        $str .= '</div>'; // Cell.
        $str .= '</div>'; // Row.
        $str .= '</div>'; // Table.

        return $str;
    }

    public function count_by_states($isactive, $tickets, $alltickets) {

        $str = '';

        $str .= '<table width="80%" class="generaltable">';
        $str .= '<tr>';
        $str .= '<th width="40%" align="left">'.get_string('status', 'tracker').'</th>';
        $str .= '<th width="30%" align="right">'.get_string('count', 'tracker').'</th>';
        $str .= '<th width="30%" align="right"></th>';
        $str .= '</tr>';
        foreach (array_keys($this->statuskeys) as $key) {

            if ($isactive) {
                $exclude = in_array($key, array(ABANDONNED, RESOLVED, VALIDATED, TRANSFERED));
            } else {
                $exclude = !in_array($key, array(ABANDONNED, RESOLVED, VALIDATED, TRANSFERED));
            }

            if ($exclude) {
                continue;
            }
            $str .= '<tr>';
            $str .= '<td width="40%" align="left" class="status-'.$this->statuscodes[$key].'">'.$this->statuskeys[$key].'</td>';
            $str .= '<td width="30%" align="right">'.(0 + @$tickets[$key]).'</td>';
            $rate = ($alltickets) ? sprintf("%2d", ((0 + @$tickets[$key]) / $alltickets) * 100) : '0';
            $str .= '<td width="30%" align="right">'.$rate.' %</td>';
            $str .= '</tr>';
        }
        $str .= '</table>';

        return $str;
    }

    public function count_by_assignee($statsbyassignee, $alltickets) {
        if (empty($statsbyassignee)) {
            return $this->output->notification(get_string('noticketsorassignation', 'tracker'));
        } else {
            $str = '<table width="95%" class="generaltable">';
            $line = 0;
            foreach ($statsbyassignee as $r) {
                if (empty($r->name)) {
                    $r->name = get_string('unassigned', 'tracker');
                }
                $str .= '<tr class="r'.$line.'">';
                $str .= '<td width="50%" align="left">'.$r->name.'</td>';
                $str .= '<td width="10%" align="right" class="tracker-report-assignee">'.$r->sum.'</td>';
                $str .= '<td width="40%">';
                foreach ($r->status as $statkey => $subresult) {
                    $statcode = $this->statuscodes[$statkey];
                    $str .= '<span class="status-'.$statcode.'">'.$subresult.'</span> ';
                }
                $str .= '</td>';

                $str .= '</tr>';
                $line = ($line + 1) % 2;
            }
            $str .= '</table>';

            return $str;
        }
    }

    public function count_by_reporter($statsbyreporter, $alltickets) {
        if (empty($statsbyreporter)) {
            return $this->output->notification(get_string('notickets', 'tracker'));
        } else {
            $str = '<table width="95%" class="generaltable">';
            $line = 0;
            foreach ($statsbyreporter as $r) {
                $str .= '<tr class="r'.$line.'">';
                $str .= '<td width="50%" align="left">'.$r->name.'</td>';
                $str .= '<td width="10%" align="right" class="report-status-reporter">'.$r->sum.'</td>';
                $str .= '<td width="40%">';
                foreach ($r->status as $statkey => $subresult) {
                    $statcode = $this->statuscodes[$statkey];
                    $str .= '<span class="status-'.$statcode.'">'.$subresult.'</span> ';
                }
                $str .= '</td>';
                $str .= '</tr>';
                $line = ($line + 1) % 2;
            }
            $str .= '</table>';

            return $str;
        }
    }
}

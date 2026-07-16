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
 * Version details
 *
 * @package    block_course_ascendants
 * @category   blocks
 * @copyright  2012 onwards Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_tracker;

defined('MOODLE_INTERNAL') || die();

class datalist {

    protected $table;

    protected $itemidfield;

    protected $orderfield;

    protected $context;

    public function __construct($table, $itemidfield, $orderfield, $listcontext) {
        $this->table = $table;
        $this->itemidfield = $itemidfield;
        $this->orderfield = $orderfield;
        $this->context = $listcontext;
    }

    public function up($itemid) {
        global $DB;

        $idfield = $this->itemidfield;
        $orderfield = $this->orderfield;

        // 1. Get the current item we want to move
        $params = array($idfield => $itemid);
        $this->add_context($params);
        $item = $DB->get_record($this->table, $params);

        // Can't move up if it doesn't exist, or if it's already at the very top (sortorder 1 or 0)
        if (!$item || $item->$orderfield <= 1) {
            return;
        }

        // 2. Find the item directly above it (sort order - 1)
        $searchparams = array($orderfield => $item->$orderfield - 1);
        $this->add_context($searchparams);
        $previtem = $DB->get_record($this->table, $searchparams);

        // 3. Swap their sort orders and update the database
        if (!empty($previtem)) {
            $previtem->$orderfield++;
            $item->$orderfield--;
            $DB->update_record($this->table, $item);
            $DB->update_record($this->table, $previtem);
        }
    }
   public function down($itemid) {
        global $DB;

        $idfield = $this->itemidfield;
        $orderfield = $this->orderfield;

        // 1. Get the current item we want to move
        $params = array($idfield => $itemid);
        $this->add_context($params);
        $item = $DB->get_record($this->table, $params);

        if (!$item) {
            return;
        }

        // 2. Find the item directly below it (sort order + 1)
        // CRITICAL FIX: Do not search by the original item's ID!
        $searchparams = array($orderfield => $item->$orderfield + 1);
        $this->add_context($searchparams);
        $nextitem = $DB->get_record($this->table, $searchparams);

        // 3. Swap their sort orders and update the database
        if (!empty($nextitem)) {
            $nextitem->$orderfield--;
            $item->$orderfield++;
            $DB->update_record($this->table, $item);
            $DB->update_record($this->table, $nextitem);
        }
    }
    public function last_order($itemid) {
        global $DB;

        $params = array($this->itemidfield => $itemid);
        $this->add_context($params);
        $lastorder = $DB->get_field($this->table, 'MAX('.$this->orderfield.')', $params);
        return $lastorder;
    }
    public function remove($itemid) {
        global $DB;

        $idfield = $this->itemidfield;
        $orderfield = $this->orderfield;

        // 1. Get the target item to find its current sort order
        $params = array($idfield => $itemid);
        $this->add_context($params);
        $item = $DB->get_record($this->table, $params);

        if (!$item) {
            return;
        }

        $oldorder = $item->$orderfield;

        // 2. Safely delete the item from the database
        $DB->delete_records($this->table, array($idfield => $itemid));

        // 3. Find all items below it in the SAME list to close the gap
        $searchparams = array();
        $this->add_context($searchparams); 
        
        $where = array();
        $values = array();
        
        // Dynamically build the context (e.g., matching the same elementid)
        foreach ($searchparams as $field => $val) {
            $where[] = "$field = ?";
            $values[] = $val;
        }
        
        // Target only the items below the one we just deleted
        $where[] = "$orderfield > ?";
        $values[] = $oldorder;
        
        $select = implode(' AND ', $where);
        $itemstoupdate = $DB->get_records_select($this->table, $select, $values);

        // 4. Shift them all up by 1
        if ($itemstoupdate) {
            foreach ($itemstoupdate as $toupdate) {
                $toupdate->$orderfield--;
                $DB->update_record($this->table, $toupdate);
            }
        }
    }
    /**
     * Add the context to the query params
     */
    protected function add_context(&$params) {
        if (!empty($this->context)) {
            foreach ($this->context as $field => $value) {
                $params[$field] = $value;
            }
        }
    }
}

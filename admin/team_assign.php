<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Team manager ↔ cohort assignment — AJAX user search, add/remove assignments.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

require_login();
$ctx = context_system::instance();
require_capability('local/leducon:manageall', $ctx);

$action   = optional_param('action', '', PARAM_ALPHANUMEXT);
$self_url = new moodle_url('/local/leducon/admin/team_assign.php');

// AJAX user-search (before any output).
if ($action === 'search_users') {
    require_sesskey();
    header('Content-Type: application/json');
    $q = trim(optional_param('q', '', PARAM_TEXT));
    $results = [];
    if (strlen($q) >= 2) {
        $fullname_sql = $DB->sql_fullname('firstname', 'lastname');
        $like1 = $DB->sql_like($fullname_sql, ':q1', false);
        $like2 = $DB->sql_like('email',        ':q2', false);
        $sql   = "SELECT id, firstname, lastname, email
                    FROM {user}
                   WHERE deleted = 0 AND suspended = 0 AND id <> :guestid
                     AND ($like1 OR $like2)
                ORDER BY lastname ASC, firstname ASC";
        $rows = $DB->get_records_sql($sql, [
            'guestid' => guest_user()->id,
            'q1'      => '%' . $DB->sql_like_escape($q) . '%',
            'q2'      => '%' . $DB->sql_like_escape($q) . '%',
        ], 0, 15);
        foreach ($rows as $u) {
            $results[] = ['id' => (int)$u->id, 'fullname' => fullname($u), 'email' => $u->email];
        }
    }
    echo json_encode(['results' => $results]);
    die;
}

// Save new assignment.
if ($action === 'save' && confirm_sesskey()) {
    $userid   = required_param('userid', PARAM_INT);
    $cohortid = required_param('cohortid', PARAM_INT);
    if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
        redirect($self_url, get_string('invalid_user', 'local_leducon'), null, \core\output\notification::NOTIFY_WARNING);
    }
    if (!$DB->record_exists('cohort', ['id' => $cohortid])) {
        redirect($self_url, get_string('invalid_cohort', 'local_leducon'), null, \core\output\notification::NOTIFY_WARNING);
    }
    if ($DB->record_exists('local_leducon_team_managers', ['userid' => $userid, 'cohortid' => $cohortid])) {
        redirect($self_url, get_string('manager_duplicate', 'local_leducon'), null, \core\output\notification::NOTIFY_WARNING);
    }
    $DB->insert_record('local_leducon_team_managers', (object)[
        'userid' => $userid, 'cohortid' => $cohortid, 'timeassigned' => time(),
    ]);
    redirect($self_url, get_string('saved', 'local_leducon'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'delete' && confirm_sesskey()) {
    $mid = required_param('mid', PARAM_INT);
    $DB->delete_records('local_leducon_team_managers', ['id' => $mid]);
    redirect($self_url, get_string('deleted', 'local_leducon'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Load data.
$cohorts  = $DB->get_records('cohort', null, 'name ASC', 'id, name');
$fullname_sql = $DB->sql_fullname('u.firstname', 'u.lastname');
$assignments = $DB->get_records_sql(
    "SELECT m.id, m.userid, m.cohortid,
            {$fullname_sql} AS fullname, u.email, co.name AS cohort_name
       FROM {local_leducon_team_managers} m
       JOIN {user}   u  ON u.id  = m.userid
       JOIN {cohort} co ON co.id = m.cohortid
      WHERE u.deleted = 0
   ORDER BY co.name ASC, u.lastname ASC, u.firstname ASC"
);

$search_url = (new moodle_url('/local/leducon/admin/team_assign.php',
    ['action' => 'search_users', 'sesskey' => sesskey()]))->out(false);

$PAGE->set_context($ctx);
$PAGE->set_url($self_url);
$PAGE->set_title(get_string('nav_admin_managers', 'local_leducon'));
$PAGE->set_heading(get_string('nav_admin_managers', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('nav_admin_managers', 'local_leducon'));

echo $OUTPUT->header();
local_leducon_render_nav('admin', 'admin_managers', $USER->id);
echo $OUTPUT->heading(get_string('nav_admin_managers', 'local_leducon'));
echo html_writer::tag('p', get_string('admin_managers_desc', 'local_leducon'), ['class' => 'text-muted']);
?>

<div class="ld-admin-split">

<!-- Assign Manager form -->
<div class="ld-card">
  <div class="ld-card-header">
    <h3 class="ld-card-title">👤 <?php echo get_string('assign_manager', 'local_leducon'); ?></h3>
  </div>
  <div class="ld-card-body">
    <form method="post" id="assign-form">
      <input type="hidden" name="action"  value="save">
      <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
      <div class="ld-form-row">
        <label for="user-search-input"><?php echo get_string('manager_user', 'local_leducon'); ?> <span class="text-danger">*</span></label>
        <div class="ld-user-search-wrap" id="user-search-wrap">
          <span class="ld-user-search-icon" aria-hidden="true">🔍</span>
          <input type="search" id="user-search-input" class="ld-input ld-user-search-input"
                 name="ld_manager_lookup_<?php echo sesskey(); ?>"
                 placeholder="<?php echo get_string('user_search_placeholder', 'local_leducon'); ?>"
                 autocomplete="new-password" role="combobox" aria-autocomplete="list" aria-controls="user-dropdown">
          <ul class="ld-user-dropdown" id="user-dropdown" role="listbox" hidden></ul>
        </div>
        <input type="hidden" name="userid" id="user-id-hidden" required>
        <div id="user-selected-display" class="ld-user-selected" style="display:none"></div>
        <small class="text-muted"><?php echo get_string('user_search_hint', 'local_leducon'); ?></small>
      </div>
      <div class="ld-form-row">
        <label for="cohort-select"><?php echo get_string('cohort', 'local_leducon'); ?> <span class="text-danger">*</span></label>
        <select name="cohortid" id="cohort-select" required class="ld-select">
          <option value=""><?php echo get_string('select_cohort', 'local_leducon'); ?></option>
          <?php foreach ($cohorts as $co): ?>
            <option value="<?php echo (int)$co->id; ?>"><?php echo s(format_string($co->name)); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ld-action-bar mt-1">
        <button type="submit" class="ld-btn ld-btn-primary" id="assign-btn" disabled>
          <?php echo get_string('assign', 'local_leducon'); ?>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Current Assignments table -->
<div class="ld-card">
  <div class="ld-card-header">
    <h3 class="ld-card-title">👥 <?php echo get_string('current_assignments', 'local_leducon'); ?></h3>
    <?php if ($assignments): ?>
    <span class="ld-badge ld-badge-primary"><?php echo count($assignments); ?></span>
    <?php endif; ?>
  </div>
  <div class="ld-card-body-flush">
    <?php if ($assignments): ?>
    <div class="table-responsive">
    <table class="ld-table table table-sm table-hover mb-0">
      <thead>
        <tr>
          <th><?php echo get_string('manager_user', 'local_leducon'); ?></th>
          <th><?php echo get_string('cohort', 'local_leducon'); ?></th>
          <th style="width:110px"><?php echo get_string('actions', 'local_leducon'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($assignments as $a): ?>
        <tr>
          <td><strong><?php echo s($a->fullname); ?></strong><br><small class="text-muted"><?php echo s($a->email); ?></small></td>
          <td><span class="ld-badge ld-badge-secondary"><?php echo s(format_string($a->cohort_name)); ?></span></td>
          <td>
            <a href="<?php echo (new moodle_url($self_url, ['action' => 'delete', 'mid' => $a->id, 'sesskey' => sesskey()]))->out(); ?>"
               class="ld-btn ld-btn-sm ld-btn-danger"
               onclick="return confirm('<?php echo get_string('confirm_delete', 'local_leducon'); ?>');">
              <?php echo get_string('remove', 'local_leducon'); ?>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?>
    <div class="ld-card-body"><p class="text-muted mb-0"><?php echo get_string('no_assignments', 'local_leducon'); ?></p></div>
    <?php endif; ?>
  </div>
</div>

</div>

<script>
(function () {
    'use strict';
    var SEARCH_URL = <?php echo json_encode($search_url); ?>;
    var NO_RESULTS = <?php echo json_encode(get_string('user_search_noresults', 'local_leducon')); ?>;
    var input = document.getElementById('user-search-input'),
        dropdown = document.getElementById('user-dropdown'),
        hiddenId = document.getElementById('user-id-hidden'),
        selectedDiv = document.getElementById('user-selected-display'),
        assignBtn = document.getElementById('assign-btn'),
        timer = null, DEBOUNCE = 280;
    function escHtml(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
    function clearSelection(){hiddenId.value='';selectedDiv.style.display='none';selectedDiv.textContent='';assignBtn.disabled=true;}
    function selectUser(id,fullname,email){
        hiddenId.value=id;input.value='';dropdown.hidden=true;dropdown.innerHTML='';
        selectedDiv.style.display='flex';
        selectedDiv.innerHTML='<span class="ld-user-sel-name">'+escHtml(fullname)+'</span>'+
            '<span class="ld-user-sel-email">'+escHtml(email)+'</span>'+
            '<button type="button" class="ld-user-sel-clear" aria-label="Clear">&#x2715;</button>';
        selectedDiv.querySelector('.ld-user-sel-clear').addEventListener('click',function(){clearSelection();input.focus();});
        assignBtn.disabled=false;
    }
    input.addEventListener('input',function(){var q=this.value.trim();clearTimeout(timer);clearSelection();
        if(q.length<2){dropdown.hidden=true;dropdown.innerHTML='';return;}
        timer=setTimeout(function(){doSearch(q);},DEBOUNCE);});
    function doSearch(q){
        fetch(SEARCH_URL+'&q='+encodeURIComponent(q),{headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){return r.json();}).then(function(data){
            dropdown.innerHTML='';
            if(!data.results||data.results.length===0){var li=document.createElement('li');li.className='ld-user-dd-empty';li.textContent=NO_RESULTS;dropdown.appendChild(li);}
            else{data.results.forEach(function(u){var li=document.createElement('li');li.setAttribute('role','option');li.className='ld-user-dd-item';
                li.innerHTML='<span class="ld-user-dd-name">'+escHtml(u.fullname)+'</span><span class="ld-user-dd-email">'+escHtml(u.email)+'</span>';
                li.addEventListener('mousedown',function(e){e.preventDefault();});
                li.addEventListener('click',(function(uid,name,mail){return function(){selectUser(uid,name,mail);};})(u.id,u.fullname,u.email));
                dropdown.appendChild(li);});}
            dropdown.hidden=false;}).catch(function(){dropdown.hidden=true;});
    }
    document.addEventListener('click',function(e){if(!document.getElementById('user-search-wrap').contains(e.target)){dropdown.hidden=true;}});
    input.addEventListener('keydown',function(e){var items=dropdown.querySelectorAll('.ld-user-dd-item');if(!items.length)return;
        var active=dropdown.querySelector('.ld-user-dd-item.focused'),idx=Array.prototype.indexOf.call(items,active);
        if(e.key==='ArrowDown'){e.preventDefault();if(active)active.classList.remove('focused');items[Math.min(idx+1,items.length-1)].classList.add('focused');}
        else if(e.key==='ArrowUp'){e.preventDefault();if(active)active.classList.remove('focused');items[Math.max(idx-1,0)].classList.add('focused');}
        else if(e.key==='Enter'){var f=dropdown.querySelector('.ld-user-dd-item.focused');if(f){e.preventDefault();f.click();}}
        else if(e.key==='Escape'){dropdown.hidden=true;}});
}());
</script>

<?php echo $OUTPUT->footer();

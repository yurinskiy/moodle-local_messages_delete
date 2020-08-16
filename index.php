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
 * Main file plugin
 *
 * @package   local_messages_delete
 * @copyright 2020, Yuriy Yurinskiy <yuriyyurinskiy@yandex.ru>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/message/externallib.php');

$deleted = optional_param('deleted', 0, PARAM_INT);

$url = new moodle_url('/local/messages_delete/index.php');
$systemcontext = $context = context_system::instance();

$pagetitle = get_string('pluginname', 'local_messages_delete');
$pageheading = format_string($SITE->fullname, true, array('context' => $systemcontext));

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pageheading);

/** Проверяем авторизован ли пользователь */
require_login();

/** Идентификатор авторизованного пользователя */
$userid = $USER->id;

/** Проверяем права пользователя */
if (!is_siteadmin()) {
    header('Location: ' . $CFG->wwwroot);
    die();
}

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);

if ($deleted) {
	global $DB;

	$sql = '
	select m.id
	  from {messages} m
	 inner join {message_conversations} mc on mc.id= m.conversationid
	 inner join {message_conversation_members} mcm on mcm.conversationid= mc.id
	  left join {message_user_actions} ua on ua.messageid = m.id
	 where m.useridfrom <> mcm.userid and (m.useridfrom = ? or mcm.userid = ?)
	   and not exists(select 1 
						from {message_user_actions} mua 
					   where mua.messageid = m.id and mua.action = ? and mua.userid = ?)
	';
	$params = [ $userid, $userid, \core_message\api::MESSAGE_ACTION_DELETED , $userid ];

	$messages = $DB->get_records_sql($sql, $params);

	$result = 0;

	foreach($messages as $message) {
		if (\core_message_external::delete_message($message->id, $userid)) {
			$result++;
		}
	}
	
	echo sprintf('<div class="alert alert-success">Удалено %s сообщений из %s</div>', $result, count($messages));
}

echo '<p>Вы уверены, что хотите удалить все ваши сообщения? Это не удалит их для других участников разговора.</p>';
echo '<form method="POST"><input name="deleted" type="hidden" value="1" />';
echo '<input type="submit" class="btn btn-primary" value="Удалить"></form>';

echo $OUTPUT->footer();
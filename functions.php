<?php

/**
 * Set cURL options
 */
function set_curl_options($ch, $target, $id = null) {
  switch ($target) {
    case 'users':
      $path = '/ocs/v1.php/cloud/users';
      break;
    case 'groups':
      $path = '/ocs/v1.php/cloud/groups';
      break;
    case 'groupfolders':
      $path = '/index.php/apps/groupfolders/folders';
      break;
    case 'capabilities':
      $path = '/ocs/v1.php/cloud/capabilities';
      break;
    default:
      $path = '';
  }

  $id = $id === null ? null : '/' . rawurlencode($id);

  curl_setopt($ch, CURLOPT_URL, $_SESSION['target_url'] . $path . $id);
  curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
  curl_setopt($ch, CURLOPT_TCP_FASTOPEN, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
  curl_setopt($ch, CURLOPT_USERPWD, $_SESSION['user_name'] . ':' . $_SESSION['user_pass']);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'OCS-APIRequest: true',
    'Accept: application/json'
  ]);
}

/**
 * Populate session array 'data_options' with all data options that can be selected
 */
function set_data_options() {
  $_SESSION['data_options'] = [
    'id' => L10N_USER_ID, 'displayname' => L10N_DISPLAYNAME,
    'email' => L10N_EMAIL, 'lastLogin' => L10N_LAST_LOGIN,
    'backend' => L10N_BACKEND, 'enabled' => L10N_ENABLED,
    'quota' => L10N_QUOTA, 'used' => L10N_QUOTA_USED,
    'percentage_used' => L10N_PERCENTAGE_USED, 'free' => L10N_QUOTA_FREE,
    'groups' => L10N_GROUPS, 'subadmin' => L10N_SUBADMIN,
    'language' => L10N_LANGUAGE, 'locale' => L10N_LOCALE
  ];
}

/**
 * Check secure outgoing connection
 */
function check_https($input_url) {
  require 'config.php';

  $error_msg = "<font color='red' face='Helvetica'>
      <hr>
        <b>".L10N_HTTP_IS_BLOCKED."</b>
        <br>".L10N_HTTPS_RECOMMENDATION."
      <hr><font color='black'>";
  if (!$https_strict)
    $error_msg .= "<br>".L10N_HTTPS_OVERRIDE_HINT."
        <br>".L10N_EG."!http://cloud.example.com</font>";
  else
    $error_msg .= "<br>".L10N_HTTPS_STRICT_MODE."</font>";

  $trimmed_url = substr($input_url,0,5);

  switch($trimmed_url) {
    case 'https':
      $output_url = $input_url;
      break;
    case 'http:':
      header('Content-Type: text/html; charset=utf-8');
      exit($error_msg);
      break;
    case '!http':
      if($https_strict) {
        header('Content-Type: text/html; charset=utf-8');
        exit($error_msg);
      }
      $output_url = ltrim($input_url,'!');
      break;
    default:
      $output_url = "https://".$input_url;
      break;
  }
  return $output_url;
}

/**
 * Remove httpx:// from given URL and return the trimmed version
 */
function removehttpx($input_url) {
  return preg_replace('(^https?://)', '', $input_url);
}
/**
 * Error handling for cURL requests
 */
function check_curl_response($ch, $data) {
  if (curl_errno($ch)) {
    switch (curl_errno($ch)) {
      case 6:
        header('Content-Type: text/html; charset=utf-8');
        exit('<font color="red"><hr><b>'.L10N_ERROR.'cURL ('.L10N_STATUSCODE.curl_errno($ch).')</b><br>'.curl_error($ch).'<font color="black"><hr>'.L10N_ERROR_CURL_CONNECTION.'</font>');
      case 51:
        header('Content-Type: text/html; charset=utf-8');
        exit('<font color="red"><hr><b>'.L10N_ERROR.'cURL ('.L10N_STATUSCODE.curl_errno($ch).')</b><br>'.curl_error($ch).'<font color="black"><hr>'.L10N_ERROR_URL.'</font>');
      default:
        header('Content-Type: text/html; charset=utf-8');
        exit('<font color="red"><hr><b>'.L10N_ERROR.'cURL ('.L10N_STATUSCODE.curl_errno($ch).')</b><br>'.curl_error($ch).'<hr></font>');
    }
  }

  if ($data === null) {
    header('Content-Type: text/html; charset=utf-8');
    exit('<font color="red"><hr><b>'.L10N_ERROR . L10N_ERROR_EMPTY_API_RESPONSE.'</b><hr></font>');
  }
  $status = $data['ocs']['meta']['statuscode'];
  switch ($status) {
    case 100:
    case 200:
      break;
    case 404:
      header('Content-Type: text/html; charset=utf-8');
      exit('<font color="red"><hr><b>'.L10N_ERROR . L10N_USER_DOES_NOT_EXIST.' ('.L10N_STATUSCODE.$status.')</b><hr></font>');
      break;
    case 997:
      header('Content-Type: text/html; charset=utf-8');
      exit('<font color="red"><hr><b>'.L10N_ERROR . L10N_AUTHENTICATION.' ('.L10N_STATUSCODE.$status.')</b><br>'.L10N_CHECK_USER_PASS.'<font color="black"><hr>'.L10N_HINT_ADMIN_OR_GROUP_ADMIN.'</font>');
    default:
      header('Content-Type: text/html; charset=utf-8');
      exit('<font color="red"><hr><b>'.L10N_ERROR . L10N_UNKNOWN.' ('.L10N_STATUSCODE.$status.')</b><hr></font>');
  }
}

/**
 * Fetch the list containing all user IDs from the server
 */
function fetch_userlist() {
  $timestamp_start = microtime(true);
  $ch = curl_init();
  set_curl_options($ch, 'users');
  $users_raw = json_decode(curl_exec($ch), true);
  check_curl_response($ch, $users_raw);
  curl_close($ch);
  if (isset($users_raw['ocs']['data']['users'])) {
    $users = $users_raw['ocs']['data']['users'];
    $_SESSION['authenticated'] = true;
  }
  $_SESSION['userlist'] = $users;
  $_SESSION['time_fetch_userlist'] = round(microtime(true) - $timestamp_start,1);
}

/**
 * Fetch the list containing all group IDs from the server
 */
function fetch_grouplist() {
  $timestamp_start = microtime(true);
  $ch = curl_init();
  set_curl_options($ch, 'groups');
  $groups_raw = json_decode(curl_exec($ch), true);
  check_curl_response($ch, $groups_raw);
  curl_close($ch);
  $groups = $groups_raw['ocs']['data']['groups'] ?? null;
  $_SESSION['grouplist'] = $groups;
  $_SESSION['time_fetch_grouplist'] = round(microtime(true) - $timestamp_start,1);
}

/**
 * Hier folgen weitere Kernfunktionen wie fetch_raw_user_data, fetch_raw_groupfolders_data, 
 * die Select/Filter-Funktionen und alles, was mit Export, CSV, Gruppen, User usw. zu tun hat.
 * 
 * Bitte Teil 3 anfordern für die Fortsetzung!
 */
/**
 * Fetch raw user data via multi-curl (parallel requests)
 */
function fetch_raw_user_data() {
  $timestamp_start = microtime(true);
  $mh = curl_multi_init();

  foreach($_SESSION['userlist'] as $key => $user_id) {
    $curl_requests[$key] = curl_init();
    set_curl_options($curl_requests[$key], 'users', $user_id);
    curl_multi_add_handle($mh, $curl_requests[$key]);
  }

  do {
    $status = curl_multi_exec($mh, $active);
    if ($active) { curl_multi_select($mh); }
  } while ($active && $status == CURLM_OK);

  foreach ($curl_requests as $key => $request) {
    $raw_user_data[] = json_decode(curl_multi_getcontent($curl_requests[$key]),true);
    curl_multi_remove_handle($mh, $curl_requests[$key]);
  }
  curl_multi_close($mh);

  $_SESSION['time_fetch_userdata'] = round(microtime(true) - $timestamp_start,1);
  $_SESSION['time_total'] = number_format(round(
                microtime(true) - $_SESSION['timestamp_script_start'], 1),1);
  $_SESSION['timestamp_data'] = date(DATE_ATOM);
  return $raw_user_data;
}

/**
 * Fetch groupfolders data, if enabled on server
 */
function fetch_raw_groupfolders_data() {
  $timestamp_start = microtime(true);
  $ch = curl_init();
  set_curl_options($ch, 'groupfolders');
  $_SESSION['raw_groupfolders_data'] = json_decode(curl_exec($ch), true);
  $_SESSION['groupfolders_active'] = isset($_SESSION['raw_groupfolders_data']);
  curl_close($ch);
  $_SESSION['time_fetch_groupfolders'] = round(microtime(true) - $timestamp_start,1);
}

/**
 * Fetch server capabilities
 */
function fetch_server_capabilities() {
  $ch = curl_init();
  set_curl_options($ch, 'capabilities');
  $_SESSION['raw_server_capabilities'] = json_decode(curl_exec($ch), true);
  check_curl_response($ch, $_SESSION['raw_server_capabilities']);
  curl_close($ch);
}

/**
 * Select/filter user data for all users
 */
function select_data_all_users($data_choices = null, $userlist = null, $format = null, $csv_delimiter = ', ') {
  $data_choices = $data_choices ?? $_SESSION['data_choices'];
  $userlist = $userlist ?? $_SESSION['userlist'];

  foreach($userlist as $key => $user_id) {
    $selected_user_data[] = select_data_single_user(
      $_SESSION['raw_user_data'][$key], $user_id, $data_choices, $format, $csv_delimiter);
  }
  return $selected_user_data;
}

/**
 * Select/filter user data for a single user
 */
function select_data_single_user(
  $data, $user_id, $data_choices, $format = null, $csv_delimiter = ', ') {
  // If data is not returned due to missing permissions (group admins) set 'N/A' instead
  if($data['ocs']['meta']['statuscode'] == 997) {
    $selected_data[] = $user_id;
    for($i = 1; $i < count($data_choices); $i++)
      $selected_data[] = 'N/A';
  }
  else {
    foreach($data_choices as $key => $item) {
      $quota = $data['ocs']['data']['quota']['quota'];
      $used = $data['ocs']['data']['quota']['used'];
      $backend = $data['ocs']['data']['backend'];

      $item_data = $item !== 'percentage_used'
          ? $data['ocs']['data'][$item]
          : ((in_array($quota, [-3, 0, 'none']) || $backend === 'Guests')
              ? 'N/A'
              : round($used / $quota * 100));
      switch($item) {
        case 'email':
          $selected_data[] = $item_data == null ? '-' : strtolower($item_data);
          break;
        case 'lastLogin':
          $selected_data[] = $item_data == 0
            ? ($format == 'csv' ? '-' : '<span style="color: red;">&#10008;</span>')
            : date("Y-m-d", substr($item_data, 0, 10));
          break;
        case 'enabled':
          $selected_data[] = $format == 'csv'
            ? $item_data
            : ($item_data == true
              ? '<span style="color: green">&#10004;</span>'
              : '<span style="color: red">&#10008;</span>');
          break;
        case 'quota':
        case 'free':
          if($backend === 'Guests') {
            $selected_data[] = 'N/A';
            break;
          }
          $item_data = $data['ocs']['data']['quota'][$item];
          $selected_data[] = in_array($item_data, [-3, 'none'], true)
              ? '∞'
              : ($format != 'csv'
                  ? format_size($item_data, 'no_filter')
                  : $item_data);
          break;
        case 'used':
          if($backend === 'Guests') {
            $selected_data[] = 'N/A';
            break;
          }
          $item_data = $data['ocs']['data']['quota'][$item];
          $selected_data[] = $format != 'csv'
              ? format_size($item_data)
              : $item_data;
          break;
        case 'subadmin':
        case 'groups':
          $selected_data[] = empty($item_data)
              ? '-'
              : ($format != 'csv'
                  ? build_csv_line($item_data, false, $csv_delimiter)
                  : build_csv_line($item_data));
          break;
        case 'locale':
          $selected_data[] = $item_data == '' ? '-' : $item_data;
          break;
        default:
          $selected_data[] = $item_data;
      }
    }
  }
  return $selected_data;
}
/**
 * Select/filter users by filter options
 */
function select_data_all_users_filter($filter_by, $conditions, $filter_option = null) {
  foreach($_SESSION['userlist'] as $key => $user_id) {

    if($filter_by == 'quota' || $filter_by == 'used' || $filter_by == 'free') {
      $item_data = $_SESSION['raw_user_data'][$key]['ocs']['data']['quota'][$filter_by];
      $limit_to_check = $conditions * 1073741824; // Gibibytes
    }
    else
      $item_data = $_SESSION['raw_user_data'][$key]['ocs']['data'][$filter_by];

    switch($filter_by) {
      case 'quota':
      case 'used':
      case 'free':
        require 'config.php';
        switch ($filter_option) {
          case 'gt':
            if($item_data > $limit_to_check)
              $selected_user_ids[] = $user_id;
            break;
          case 'lt':
            if($item_data < $limit_to_check)
              $selected_user_ids[] = $user_id;
            break;
          case 'asymp':
            if($item_data > $limit_to_check * (1 - $filter_tolerance)
                && $item_data < $limit_to_check * (1 + $filter_tolerance))
              $selected_user_ids[] = $user_id;
            break;
          case 'equals':
            if($item_data == $limit_to_check)
              $selected_user_ids[] = $user_id;
            break;
        }
        break;
      case 'lastLogin':
        $lastLogin = substr($item_data, 0, 10);

        if($lastLogin >= strtotime($conditions[0])
            && $lastLogin <= strtotime($conditions[1].' +1 day'))
          $selected_user_ids[] = $user_id;
        break;
      case 'groups':
      case 'subadmin':
        if(in_array($conditions, $item_data))
          $selected_user_ids[] = $user_id;
        break;
    }
  }

  if(empty($selected_user_ids))
    $selected_user_ids = [''];

  return $selected_user_ids;
}

/**
 * Nutzer nach gesetzten Filtern filtern
 */
function filter_users() {
  if(!empty($_SESSION['filters_set'])) {
    $filter_conditions_ll = [$_SESSION['filter_ll_since'], $_SESSION['filter_ll_before']];

    $uids_g = in_array('filter_group_choice', $_SESSION['filters_set'])
      ? select_data_all_users_filter('groups', $_SESSION['filter_group'])
      : $_SESSION['userlist'];

    $uids_l = in_array('filter_lastLogin_choice', $_SESSION['filters_set'])
      ? select_data_all_users_filter('lastLogin', $filter_conditions_ll)
      : $_SESSION['userlist'];

    $uids_q = in_array('filter_quota_choice', $_SESSION['filters_set'])
      ? select_data_all_users_filter($_SESSION['type_quota'],
          $_SESSION['filter_quota'], $_SESSION['compare_quota'])
      : $_SESSION['userlist'];

    $user_ids = array_intersect($_SESSION['userlist'], $uids_g, $uids_l, $uids_q);
  }

  if(empty($user_ids))
    exit('No users found matching filter settings');

  return $user_ids;
}

/**
 * Find all users belonging to a given group
 */
function select_group_members($group, $format = null) {
  foreach($_SESSION['userlist'] as $key => $user_id) {
    $data = $_SESSION['raw_user_data'][$key];
    if(in_array($group, $data['ocs']['data']['groups']))
      $group_members[] = [$user_id, $data['ocs']['data']['displayname']];
  }
  return $group_members ?? null;
}

/**
 * Quota summieren
 */
function calculate_quota() {
  $_SESSION['quota_total_assigned'] = 0;
  $_SESSION['quota_total_free'] = 0;
  $_SESSION['quota_total_used'] = 0;

  foreach($_SESSION['raw_user_data'] as $user_data) {
    $_SESSION['quota_total_used'] += $user_data['ocs']['data']['quota']['used'];
    $_SESSION['quota_total_free'] += $user_data['ocs']['data']['quota']['free'];
    $quota_assigned = $user_data['ocs']['data']['quota']['quota'];
    $_SESSION['quota_total_assigned'] += $quota_assigned > 0 ? $quota_assigned : 0;
    $_SESSION['quota_total_assigned_infin'] = ($quota_assigned == -3);
  }

  if(!empty($_SESSION['groupfolders_active'])) {
    $_SESSION['quota_groupfolders_used'] = 0;
    $_SESSION['quota_groupfolders_assigned'] = 0;
    foreach($_SESSION['raw_groupfolders_data']['ocs']['data'] as $groupfolder) {
      $_SESSION['quota_groupfolders_used'] += $groupfolder['size'];
      $_SESSION['quota_groupfolders_assigned'] += $groupfolder['quota'];
    }
  }
}

/**
 * Statusanzeigen und Tabellen (print_status_success, print_status_overview, build_table_user_data, build_table_group_data, build_table_groupfolder_data)
 * ... (Hier geht es mit den Anzeige-/Exportfunktionen weiter)
 */
/**
 * Statusanzeige bei erfolgreicher Verbindung
 */
function print_status_success() {
  echo '
    <hr>'.L10N_CONNECTED_TO_SERVER.removehttpx($_SESSION['target_url'])
    .' <span style="color: green">&#10004;</span>'
    .'<br>'.L10N_DOWNLOADED.' '.count($_SESSION['raw_user_data']).' '
    .L10N_USERS_AND.' '.count($_SESSION['grouplist']).' '
    .L10N_GROUPS_IN.' '.$_SESSION['time_total'].' '.L10N_SECONDS
    .'<br>Timestamp: '.$_SESSION['timestamp_data'].
    '<hr><span style="color: darkgreen;">'
    .L10N_ACCESS_TO_ALL_MENU_OPTIONS.'</span>';
}

/**
 * Statusanzeige (kurz/lang)
 */
function print_status_overview($scope = "quick") {
  $infinite = $_SESSION['quota_total_assigned_infin']
    ? " (+ &infin;)"
    : "";

  if($scope == "quick") {
    echo "<hr>
      <a class='no_show_link' href='{$_SESSION['target_url']}' target='_blank'>"
      .removehttpx($_SESSION['target_url'])."</a>
    <hr>";
  } else {

    fetch_server_capabilities();

    $_SESSION['server_version_string'] =
      $_SESSION['raw_server_capabilities']['ocs']['data']['version']['string'];

    echo "<hr>
      <a class='no_show_link' href='{$_SESSION['target_url']}' target='_blank'>"
      .removehttpx($_SESSION['target_url'])."</a>
       (".L10N_NEXTCLOUD." v{$_SESSION['server_version_string']})
    <hr>
    <table class='status'>
      <tr>
        <td colspan=2 style='min-width: 15em;'><b>Overall count</b></td>
      </tr>
      <tr>
        <td>".L10N_USERS."</td>
        <td class='align_r'>{$_SESSION['user_count']}</td>
      </tr>
      <tr>
        <td>".L10N_GROUPS."</td>
        <td>{$_SESSION['group_count']}</td>
      </tr>";

    if($_SESSION['groupfolders_active'])
      echo "<tr>
        <td>".L10N_GROUPFOLDERS."</td>
        <td>{$_SESSION['groupfolders_count']}</td>
      </tr>";

    echo "
    </table>
    <hr>
    <table class='status'>
      <tr>
        <td colspan=2 style='min-width: 15em;'><b>".L10N_USERS."</b></td>
      </tr>
      <tr>
        <td>".L10N_QUOTA_USED."</td>
        <td>".format_size($_SESSION['quota_total_used'])."</td>
      </tr>
      <tr>
        <td>".L10N_QUOTA."</td>
        <td>".format_size($_SESSION['quota_total_assigned'])."</td>
        <td>$infinite</td>
      </tr>
      <tr>
        <td>".L10N_QUOTA_FREE."</td>
        <td>".format_size($_SESSION['quota_total_free'])."</td>
      </tr>";

      if($_SESSION['groupfolders_active'])
        echo "<tr style='height: 10px'>
            <td></td>
          </tr>
          <tr>
            <td colspan=2 style='min-width: 15em;'><b>".L10N_GROUPFOLDERS."</b></td>
          </tr>
          <tr>
            <td>".L10N_QUOTA_USED."</td>
            <td>".format_size($_SESSION['quota_groupfolders_used'])."</td>
          </tr>
          <tr>
            <td>".L10N_QUOTA."</td>
            <td>".format_size($_SESSION['quota_groupfolders_assigned'])."</td>
          </tr>
        </table>";

    echo "<hr><table class='status'>
          <tr>
            <td colspan=2 style='min-width: 15em;'><b>".L10N_EXECUTION_TIMES."</b></td>
          </tr>
          <tr>
            <td>".L10N_FETCH_USERLIST."</td>
            <td>{$_SESSION['time_fetch_userlist']} s</td>
          </tr>
          <tr>
            <td>".L10N_FETCH_GROUPLIST."</td>
            <td>{$_SESSION['time_fetch_grouplist']} s</td>
          </tr>
          <tr>
            <td>".L10N_FETCH_GROUPFOLDERS."</td>
            <td>{$_SESSION['time_fetch_groupfolders']} s</td>
          </tr>
          <tr>
            <td>".L10N_FETCH_USERDATA."</td>
            <td>{$_SESSION['time_fetch_userdata']} s</td>
          </tr>
          </table><hr>"
          .L10N_DATA_RETRIEVED." {$_SESSION['timestamp_data']}";
  }
}

/**
 * CSV-Tabellen und CSV-Funktionen (build_csv_file, build_csv_user_data, build_group_data, build_csv_line, format_size)
 * ... (Hier kann Teil 6 mit Export- und Hilfsfunktionen angefordert werden)
 */
/**
 * Schreibe Nutzerdaten als CSV
 */
function build_csv_user_data($selected_user_data, $delimiter = ";") {
  $lines = [];
  foreach($selected_user_data as $user) {
    $line = [];
    foreach($user as $field) {
      $line[] = is_array($field) ? implode(', ', $field) : $field;
    }
    $lines[] = implode($delimiter, $line);
  }
  return implode("\n", $lines);
}

/**
 * Schreibe Gruppendaten als CSV
 */
function build_csv_group_data($group_data, $delimiter = ";") {
  $lines = [];
  foreach($group_data as $group) {
    $line = [];
    foreach($group as $field) {
      $line[] = is_array($field) ? implode(', ', $field) : $field;
    }
    $lines[] = implode($delimiter, $line);
  }
  return implode("\n", $lines);
}

/**
 * Schreibe Zeile für CSV
 */
function build_csv_line($array, $quote = true, $delimiter = ", ") {
  if(empty($array)) return '';
  $escaped = array_map(function($v) use ($quote) {
    return $quote ? '"' . str_replace('"', '""', $v) . '"' : $v;
  }, $array);
  return implode($delimiter, $escaped);
}

/**
 * Formatiere Byte-Größen als lesbarer String
 */
function format_size($bytes, $no_filter = false) {
  if($bytes === 'none' || $bytes === -3) return '∞';
  if($bytes === 0) return '0 B';
  $sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
  $i = floor(log($bytes, 1024));
  return round($bytes / pow(1024, $i), 2).' '.$sizes[$i];
}

/**
 * Hilfsfunktion: Session sichern
 */
function session_secure_start() {
  if(session_status() === PHP_SESSION_NONE) {
    session_start();
    session_regenerate_id();
  }
}

/**
 * Mailto-Link für ausgewählte Nutzer erstellen
 */
function build_mailto_list($mode, $userlist) {
  $emails = [];
  foreach($userlist as $user_id) {
    $key = array_search($user_id, $_SESSION['userlist']);
    if ($key !== false) {
      $email = $_SESSION['raw_user_data'][$key]['ocs']['data']['email'] ?? '';
      if (!empty($email)) $emails[] = $email;
    }
  }
  $mailto = "mailto:?";
  if($mode === 'bcc') $mailto .= "bcc=";
  else if($mode === 'cc') $mailto .= "cc=";
  else $mailto .= "to=";

  $mailto .= urlencode(implode(',', $emails));
  return $mailto;
}

/**
 * Fix für PHP 8.3: Initialisierung von $filter_group und $_SESSION['filters_set']
 */
function check_and_set_filter($filter) {
  include 'config.php';

  if (!isset($filter_group)) {
    $filter_group = $_SESSION['filter_group'] ?? null;
    if ($filter_group === null && isset($_SESSION['grouplist'][0])) {
      $filter_group = $_SESSION['grouplist'][0];
    }
  }

  switch($filter) {
    case 'group':
      if($filter_group && empty($_SESSION['group_filter_checked_by_config'])) {
        $_SESSION['group_filter_checked_by_config'] = true;
        return " checked";
      }
      $chosen_filter = 'filter_group_choice';
      break;
    case 'lastLogin':
      $chosen_filter = 'filter_lastLogin_choice';
      break;
    case 'quota':
      $chosen_filter = 'filter_quota_choice';
      break;
  }

  if (empty($_SESSION['filters_set']) || !is_array($_SESSION['filters_set'])) {
    return;
  }
  if(in_array($chosen_filter, $_SESSION['filters_set'])) {
    return " checked";
  }
}
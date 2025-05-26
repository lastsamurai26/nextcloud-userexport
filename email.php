<?php

  $active_page = 'email';
  require_once 'functions.php';
  include_once 'config.php';

  session_secure_start();

  require_once 'l10n/' . ($_SESSION['language'] ?? 'en') . '.php';

  if (empty($_SESSION['authenticated'])) {
    header('Content-Type: text/html; charset=utf-8');
    exit('<br>' . L10N_CONNECTION_NEEDED);
  }

  // Set defaults if not set
  if (!isset($_SESSION['message_mode'])) $_SESSION['message_mode'] = 'bcc';
  if (!isset($_SESSION['filter_ll_since'])) $_SESSION['filter_ll_since'] = '1970-01-01';
  if (!isset($_SESSION['filter_ll_before'])) $_SESSION['filter_ll_before'] = date('Y-m-d');
  if (!isset($_SESSION['filter_group'])) $_SESSION['filter_group'] = $_SESSION['grouplist'][0] ?? '';
  if (!isset($_SESSION['filter_quota'])) $_SESSION['filter_quota'] = $filter_quota ?? 20;
  if (!isset($_SESSION['type_quota'])) $_SESSION['type_quota'] = 'used';
  if (!isset($_SESSION['compare_quota'])) $_SESSION['compare_quota'] = 'gt';

  if (isset($_POST['submit'])) {
    $_SESSION['message_mode'] = $_POST['message_mode'] ?? $_SESSION['message_mode'];
    $_SESSION['filters_set'] = array_keys($_POST, 'set_filter');
    $_SESSION['filter_group'] = $_POST['filter_group'] ?? $_SESSION['filter_group'];
    $_SESSION['filter_quota'] = $_POST['filter_quota'] ?? $_SESSION['filter_quota'];
    $_SESSION['type_quota'] = $_POST['type_quota'] ?? $_SESSION['type_quota'];
    $_SESSION['compare_quota'] = $_POST['compare_quota'] ?? $_SESSION['compare_quota'];

    $_SESSION['filter_ll_since'] = isset($_POST['filter_ll_since']) && $_POST['filter_ll_since'] !== ""
        ? $_POST['filter_ll_since']
        : '1970-01-01';

    $_SESSION['filter_ll_before'] = isset($_POST['filter_ll_before']) && $_POST['filter_ll_before'] !== ""
        ? $_POST['filter_ll_before']
        : date('Y-m-d');

    $userlist = !empty($_SESSION['filters_set'])
        ? filter_users()
        : $_SESSION['userlist'];

    header("Location: " . build_mailto_list($_SESSION['message_mode'], $userlist));
    exit;
  }
?>
<head>
  <link rel="stylesheet" type="text/css" href="style.php">
  <meta charset="UTF-8">
  <title>Nextcloud Userexport</title>
</head>

<body>
  <?php
    include 'navigation.php';

    echo "<html lang='" . htmlspecialchars($_SESSION['language']) . "'>";

    print_status_overview();

    echo "<form method='post'>
            <br>
            <table>
            <tr>
              <td style='padding-bottom: 1em;'><u>" . L10N_SEND_AS . "</u></td>
              <td style='padding: 0.3em; padding-bottom: 1em;'>";

    $values = ['bcc', 'cc', 'to'];
    foreach ($values as $mode) {
      echo "<input type='radio' name='message_mode' id='$mode' value='$mode'";
      if ($mode == $_SESSION['message_mode']) echo " checked";
      echo "> <label for='$mode'>$mode</label>";
    }

    echo "</td></tr>
          <tr>
            <td style='padding-bottom: 1em;'><u>" . L10N_SEND_TO . "</u></td>
            <td style='padding: 0.3em; padding-bottom: 1em;'>
              <input type='radio' name='filter_group_choice' value='all_users' checked>
                <label for='filter_group_choice'>" . L10N_ALL_USERS . "</label>
              <input type='radio' name='filter_group_choice' value='set_filter'" . check_and_set_filter('group') . ">
                <label for='filter_group_choice'>" . L10N_GROUP . "</label>
              <select name='filter_group'>";
                  foreach ($_SESSION['grouplist'] as $item) {
                    $selected = ($item == $_SESSION['filter_group']) ? ' selected' : '';
                    echo "<option value='" . htmlspecialchars($item) . "'$selected>$item</option>";
                  }
    echo    "</select>
            </td>
          </tr>
          <tr>
            <td><u>" . L10N_FILTER_BY . "<u></td>
            <td style='padding: 0.3em;'>
              <input type='checkbox' name='filter_lastLogin_choice' value='set_filter'" . check_and_set_filter('lastLogin') . ">
                <label for='filter_lastLogin_choice'>" . L10N_LAST_LOGIN_BETWEEN . " </label>
              <input type='date' name='filter_ll_since' value='" . htmlspecialchars($_SESSION['filter_ll_since']) . "'>" . L10N_AND . "
              <input type='date' name='filter_ll_before' value='" . htmlspecialchars($_SESSION['filter_ll_before']) . "'>
            </td>
          </tr>
          <tr>
            <td></td>
            <td style='padding: 0.3em;'>
              <input type='checkbox' name='filter_quota_choice' value='set_filter'" . check_and_set_filter('quota') . ">
                <label for='filter_quota_choice'>" . L10N_DISK_SPACE . " </label>
                <select name='type_quota'>
                  <option value='used'" . ($_SESSION['type_quota'] == 'used' ? " selected" : "") . ">" . L10N_USED . "</option>
                  <option value='quota'" . ($_SESSION['type_quota'] == 'quota' ? " selected" : "") . ">" . L10N_ASSIGNED . "</option>
                  <option value='free'" . ($_SESSION['type_quota'] == 'free' ? " selected" : "") . ">" . L10N_FREE . "</option>
                </select>
                <select name='compare_quota'>
                  <option value='gt'" . ($_SESSION['compare_quota'] == 'gt' ? " selected" : "") . ">&gt;</option>
                  <option value='lt'" . ($_SESSION['compare_quota'] == 'lt' ? " selected" : "") . ">&lt;</option>
                  <option value='asymp'" . ($_SESSION['compare_quota'] == 'asymp' ? " selected" : "") . ">&asymp;</option>
                  <option value='equals'" . ($_SESSION['compare_quota'] == 'equals' ? " selected" : "") . ">&equals;</option>
                </select>
                <input style='width: 6em;' type='number' min=0.5 step=0.5 name='filter_quota' value='" . htmlspecialchars($_SESSION['filter_quota']) . "'> GB
            </td>
          </tr>
        </table>
        <br>
        <input id='button-email' type='submit' name='submit' value='" . L10N_CREATE_LIST . "'>
      </form>";
  ?>
</body>
</html>
<?php

  $active_page = 'groups';
  require_once 'functions.php';
  include_once 'config.php';

  session_secure_start();

  require_once 'l10n/'.$_SESSION['language'].'.php';

  // --- Anpassung: $csv_headers initialisieren!
  $csv_headers = '';
  if (isset($_POST['csv_headers'])) {
    $csv_headers = $_POST['csv_headers'];
    $_SESSION['csv_headers'] = $csv_headers;
  } elseif (isset($_SESSION['csv_headers'])) {
    $csv_headers = $_SESSION['csv_headers'];
  } else {
    $csv_headers = 'true'; // Default: Header an
  }

  echo "<html lang='{$_SESSION['language']}'>";

?>

  <head>
    <link rel="stylesheet" type="text/css" href="style.php">
    <meta charset="UTF-8">
    <title>Nextcloud Userexport</title>
  </head>

  <body>
    <?php

      include 'navigation.php';

      if(!$_SESSION['authenticated']) {
        header('Content-Type: text/html; charset=utf-8');
        exit('<br>'.L10N_CONNECTION_NEEDED);
      }

      print_status_overview();

    ?>

    <form method="post" action="groups_detail.php">
    <br><u><?php echo L10N_FORMAT_AS ?></u>
    <input type='radio' name='export_type' value='table' checked="checked">
      <?php echo L10N_TABLE ?>
    <input type='radio' name='export_type' value='csv'> <?php echo L10N_CSV ?>
    <br><br>
    <button id="button-display" type='submit' name='submit'
      value='display'><?php echo L10N_DISPLAY ?></button>
    <br><br><br>
    <u><?php echo L10N_COLUMN_HEADERS ?></u>
    <input type='radio' name='csv_headers' value='true'
      <?php if ($csv_headers == 'true' || $csv_headers === '' || $csv_headers === null)
        echo 'checked="checked"'; ?>> <?php echo L10N_YES ?>
    <input type='radio' name='csv_headers' value='false'
      <?php if ($csv_headers == 'false')
        echo 'checked="checked"'; ?>> <?php echo L10N_NO ?>
    <br><br>
    <button id="button-download" type='submit' name='submit'
      value='download'><?php echo L10N_DOWNLOAD_CSV ?></button>
    </form>
  </body>
</html>
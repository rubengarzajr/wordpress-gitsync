<?php
  /*
  Plugin Name: Git Sync
  Plugin URI:
  description: This pluging gets themes tagged as release from git
  Version: 1.1.0
  Author: Ruben Garza, Jr.
  Author URI:
  License: GPL2
  */

  $gitsync_debug_mode = FALSE;

  // add_action('admin_enqueue_scripts', 'my_admin_enqueue');
  // function my_admin_enqueue($hook_suffix) {
  //   if($hook_suffix == 'toplevel_page_git-sync') {
  //   }
  // }

  function gitsync_log($str,$numChar,$char){
    global $gitsync_debug_mode;
    if (!$gitsync_debug_mode){return;}
    $len = strlen($str);
    if($len % 2 !== 0){
      $str .= " ";
      $len ++;
    }
    $len += 2;
    $str  = " " . $str . " ";
    $out  = "";
    for ($x = 0; $x < ($numChar-$len)/2; $x++) { $out .= $char; }
    $line = '';
    for ($x = 0; $x < $numChar; $x++) { $line .= $char; }

    error_log( print_r($line, true ) );
    error_log( print_r($out . $str . $out, true ) );
    error_log( print_r($line, true ) );
    error_log( print_r('', true ) );
  }

  function gitsync_log_multi($arr){
    global $gitsync_debug_mode;
    if (!$gitsync_debug_mode){return;}

    error_log( print_r('', true ) );
    foreach ($arr as $note) {
      error_log( print_r($note, true ) );
    }
    error_log( print_r('', true ) );
  }

  function gitsync_js_message($title, $message){
    if ($title !== '') {
    ?>
    <script type="text/javascript">
      gitsync.title   += '<?php echo $title; ?>';
      gitsync.message += '<?php echo $message; ?>';
    </script>
    <?php
    }
  }

  function gitsync_getRepo($theme){
    $args = array();
    if ($theme->token !=='') {
      $args = array(
        'headers' => array(
          'Authorization' => 'token ' . $theme->token,
          'Accept' => 'application/vnd.github.v3+json'
        ),
      );
    }
    $parse = parse_url($theme->uri);
    $uri   = "https://api.{$parse['host']}/repos{$parse['path']}/releases";
    $githubAPIResult = wp_remote_retrieve_body( wp_remote_get( $uri, $args ) );
    $hasReleases = FALSE;
    $message = '';

    if ( gitsync_isJson($githubAPIResult) ) {
      $githubAPIResult = @json_decode( $githubAPIResult );

      if (!is_array($githubAPIResult)){
        if (property_exists($githubAPIResult,'message')){
          gitsync_log_multi(['message',$githubAPIResult->message]);
          $message = $githubAPIResult->message;
        }
      } else {
        if (count($githubAPIResult) > 0){ $hasReleases = TRUE; }
      }
    }

    $to_return = new stdClass();
    $to_return->result      = $githubAPIResult;
    $to_return->args        = $args;
    $to_return->parse       = $parse;
    $to_return->message     = $message;
    $to_return->hasReleases = $hasReleases;
    return $to_return;
  }

  function gitsync_isJson($string){
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
  }

  function gitsync_get_options(){
    $options = get_option('gitsyncplugin');
    if ($options === FALSE){
      $options = '{"themes":[],"plugins":[]}';
      update_option('gitsyncplugin', $options);
    }
    return json_decode($options);
  }

  function gitsync_remove_directory($target){
    $ok = TRUE;
    if (!file_exists($target)) { return; }
    $directory = new RecursiveDirectoryIterator($target,  FilesystemIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
      if (is_dir($file)) {
        rmdir($file);
      } else {
        if (!unlink($file)){
          $ok = FALSE;
        }
      }
    }
    rmdir($target);
    return $ok;
  }

  function gitsync_update_githubsync_data($options){
    update_option('gitsyncplugin', json_encode($options));
  }

  // Handle form submissions
  add_action( 'init', 'gitsync_submit_form' );
  function gitsync_submit_form() {

    gitsync_log_multi(['Current Page: '. $_SERVER['REQUEST_URI']]);
    if (!str_contains($_SERVER['REQUEST_URI'], 'page=git-sync')) {
        return;
    }

    ?>
    <script type="text/javascript">
      gitsync = {
        title:'',
        message:''
      };
    </script>
    <?php

    $options = gitsync_get_options();

    // ----- Form: ADD a theme -----
    if( isset( $_POST['add_a_theme'] ) ) {
      gitsync_log('Add a Theme',  24, '-');
      $error = FALSE;

      gitsync_log_multi(['post',$_POST]);

      if (!isset($_POST['furi']) || $_POST['furi'] == '') { $error = TRUE; }
      $temp_theme        = new stdClass();
      $temp_theme->uri   = esc_url_raw($_POST['furi']);
      $temp_theme->token = sanitize_text_field($_POST['ftok']);
      $message = 'Could not add this theme. Please check the URL address.';
      $repo_result = gitsync_getRepo($temp_theme);
      if ($repo_result->message != '') {$message = $repo_result->message;}
      if ($error || $repo_result->message != '') {
        gitsync_js_message('THEME ADD ERROR', $message);
        gitsync_log_multi(['THEME ADD ERROR', $message]);
        return;
      }
      $newTheme = array("uri"=>esc_url_raw($_POST['furi']), "token"=>sanitize_text_field($_POST['ftok']));
      array_push($options->themes, $newTheme);
      gitsync_update_githubsync_data($options);
      gitsync_js_message('THEME ADDED', 'Theme at ' . esc_url_raw($_POST['furi']) . ' was added.');
    }

    // ----- Form: SYNC a theme -----
    if( isset( $_POST['sync_a_theme'] ) ) {
      gitsync_log('Sync a Theme', 24, '-');
      $theme = $options->themes[sanitize_text_field($_POST['key'])];

      if (!empty($_POST['reposync'])) {
        $repoSync = sanitize_text_field($_POST['reposync']);
        $args = array();
        if ($theme->token !=='') {
          $args = array(
            'headers' => array(
              'Authorization' => 'token ' . $theme->token,
            ),
          );
        }

        $parse = parse_url($theme->uri);
        $uri   = "https://api.{$parse['host']}/repos{$parse['path']}/zipball/{$repoSync}";
        $parts = explode("/",$parse['path']);

        gitsync_log_multi(['Path:',$parse['path'],'URI',$uri]);
        gitsync_log_multi(['Parts:',$parts]);

        $data = wp_remote_get( $uri, $args );
        $body  = $data['body'];
        $file = plugin_dir_path( __FILE__ ). 'temp/' . $repoSync  . '.zip';

        $fp = fopen($file, "w");

        if ( $fp ) {
          fwrite($fp, $body);
          fclose($fp);

          $temp_dir = plugin_dir_path( __FILE__ ). 'temp/' . $repoSync ;
          gitsync_remove_directory($temp_dir);
          mkdir($temp_dir);

          $zip = new ZipArchive;
          if ($zip->open($file) === TRUE) {
              $zip->extractTo(plugin_dir_path( __FILE__ ) . 'temp/' . $repoSync);
              $zip->close();
          } else {
              gitsync_log_multi(['Error:','Zip Error']);
          }

          unlink($file);

          $temp_find = [];
          if ($handle = opendir($temp_dir)) {
            $blacklist = array('.', '..');
            while (false !== ($file = readdir($handle))) {
              if (!in_array($file, $blacklist)) {
                array_push($temp_find, $file);
              }
            }
            closedir($handle);
          }
          rename($temp_dir . '/'. $temp_find[0], $temp_dir . '/' . end($parts));
          gitsync_remove_directory(get_theme_root() . '/' .end($parts));
          rename($temp_dir . '/' . end($parts), get_theme_root() . '/' .end($parts) );
          gitsync_remove_directory(plugin_dir_path( __FILE__ ). 'temp/' . $repoSync);
          gitsync_js_message('THEME SYNCED', 'Synced to ' . $repoSync . '.');
        } else {
          gitsync_js_message('THEME NOT SYNCED', 'Plugin needs permission to write to wp-content/plugins/gitsync/temp directory.');
        }
      } else {
        gitsync_js_message('THEME NOT SYNCED', 'Release not found.');
        gitsync_log_multi(['NO RELEASE!']);
      }
    }

    // ----- Form: DELETE a theme -----
    if (isset( $_POST['delete_a_theme'])) {
      gitsync_log('Delete a Theme',24, '-');
      $repo_delete = esc_url_raw($_POST['repo']);
      $idx         = -1;
      $toDelete    = '';
      $post_uri    = parse_url($repo_delete);

      foreach ($options->themes as $key => $theme) {
        $test_uri = parse_url( $theme->uri );
        if ($post_uri['path'] === $test_uri['path']){
          $idx = $key;
          $toDelete = $test_uri['path'];
        };
      }

      if ($idx > -1){
        gitsync_log_multi( ['idx = '. $idx . ' ' . $toDelete]);

        $theme = $options->themes[$idx];
        $parse = parse_url($theme->uri);
        $parts = explode("/",$parse['path']);

        if (end($parts) !== ''){
          gitsync_log_multi( ["Deleting Directory: " , get_theme_root() . '/' . end($parts)]);
          $test_delete = gitsync_remove_directory(get_theme_root() . '/' .end($parts));
          if ($test_delete) {
            gitsync_js_message('THEME DELETED', 'Theme ' . end($parts) . ' removed.');
          } else {
            gitsync_js_message('THEME NOT DELETED', 'Theme ' . end($parts) . ' could not be removed.');
          }
        }

      } else {
        gitsync_js_message('THEME NOT DELETED', 'Theme not found.');
      }

    }

    // ----- Form: UNTRACK a theme -----
    if (isset( $_POST['untrack_a_theme'])) {
      gitsync_log_multi(['untrack_a_theme',$_POST['untrack_a_theme']]);
      if (isset( $_POST['key'])) {
        $san_key = sanitize_text_field($_POST['key']);
        gitsync_log_multi(['DELETE A KEY', $san_key]);
        array_splice($options->themes, $san_key, 1);
        gitsync_update_githubsync_data($options);
        gitsync_js_message('THEME REMOVED', 'Theme no longer tracked.');
      }
    }

  }


  //---------- Main ----------//

  add_action('admin_menu', 'gitsync_setup_menu');
  function gitsync_setup_menu(){
    add_menu_page( 'Git Sync', 'Git Sync', 'manage_options', 'git-sync', 'gitsync_init' );
  }

  function gitsync_init(){
    $options = gitsync_get_options();
    wp_enqueue_style('gitsync-css',plugin_dir_url('gitsync').'git-sync/gitsync.css');
    wp_enqueue_script('gitsync-script', plugin_dir_url('gitsync').'git-sync/gitsync.js', array(), 1.0, false);

    gitsync_log_multi(['Tracked Themes']);
    foreach ($options->themes as $key => $theme) {
      gitsync_log_multi( [$key . ' ' . $theme->uri . ' Token: ' . $theme->token]);
    }

    gitsync_log('RUN PLUGIN', 20, '=');
    gitsync_log_multi([date("d-m-Y h:i:s")]);

    ?>
    <h1>Git Sync</h1>

    <div class="GS-title">Add a Theme from GitHub</div>

    <form method="post" action="">
      <div class="GS-sp10">
        <label for="furi">GitHub Repo URI:</label><br>
        <input type="text" id="URI" name="furi"><br>
      </div>
      <div class="GS-sp10">
        <label for="ftok">GitHub Repo token:</label><br>
        <input type="text" id="URI" name="ftok"><br>
      </div>
      <input class="GS-add button" type="submit" name="add_a_theme" value="Add Theme" />
    </form>

    <div class="GS-title">Installed Themes</div>

    <?php
    foreach ($options->themes as $key => $theme) {
      $result_repo = gitsync_getRepo($theme);
      $parse       = $result_repo->parse;

      $theme_image = 'https://api.' . $parse['host'] . '/repos'. $parse['path'] . '/contents/screenshot.png';
      $imgGetR     = wp_remote_get($theme_image, $result_repo->args);
      $imgBody     = wp_remote_retrieve_body($imgGetR);
      $imgJson     = json_decode($imgBody);

      $githubAPIResultImage = '';
      $foundImage           = FALSE;

      if (is_object($imgJson)) {
        if (property_exists($imgJson, 'content')){
          $githubAPIResultImage = $imgJson->content;
          $foundImage = TRUE;
        }
      } else {
        // Make a default x or something
      }

      ?>
      <div id="gitsync-error-dialog" style="max-width:800px">
        <h3>Message: <?php echo $result_repo->message;?> </h3>
      </div>
      <div class="GS-repo">
        <div class="GS-repo-item"></div>
        <div class="GS-repo-item-title">
          <?php echo $theme->uri;?>
        </div>
        <div class="GS-repo-item-button">
          <form method="post" action="">
            <input type="hidden" name="repo" value="<?php echo $theme->uri;?>">
            <input class="GS-delete button" type="submit" name="delete_a_theme" value="Delete Theme" />
          </form>
        </div>
        <div class="GS-repo-item-button">
          <form method="post" action="">
            <input type="hidden" name="key" value="<?php echo $key;?>">
            <input class="GS-delete button" type="submit" name="untrack_a_theme" value="Stop Tracking Theme" />
          </form>
        </div>
      </div>
      <div class="GS-itembox">
        <div class="GS-itembox-item">
          <?php
            if ($result_repo->hasReleases) {
          ?>
            <div class="GS-itemtitle">Available Releases:</div>
            <div>
            <form method="post" action="">
              <input type="hidden" name="key" value="<?php echo $key;?>">
              <select name="reposync" class="GS-reposync">
              <?php
                foreach ($result_repo->result as $release) { ?>
                  <option value="<?php echo $release->tag_name ?>"><?php echo $release->tag_name ?></option>'
                <?php } ?>
              </select>
              <br><br>
                <input class="button" type="submit" name="sync_a_theme" value="Sync to this release" />
            </form>
          <?php } else { ?>
            <div class="GS-itemtitle">No Releases Found</div>
            <div>
          <?php } ?>
          </div>
          <br>
        </div>
        <div class="GS-itembox-item">
          <?php if ($foundImage){ ?>
          <img class="GS-preview-image" src="<?php echo 'data:image/png;base64,' . $githubAPIResultImage ?>"></img>
        <?php } else {?>
          <div class="GS-no-image">No Image Found</div>
        <?php }?>
        </div>
      </div>
    <?php
    }
  }

?>

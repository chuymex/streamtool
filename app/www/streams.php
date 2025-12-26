<?php
include('config.php');
logincheck();

$setting = Setting::first();

$message = [];
$title   = "Manage Streams";

/**
 * ACCIONES INDIVIDUALES POR GET (start/stop/restart/delete)
 */
if (isset($_GET['start'])) {
    start_stream($_GET['start']);
    $message = ['type' => 'success', 'message' => 'Stream started'];
} else if (isset($_GET['stop'])) {
    stop_stream($_GET['stop']);
    $message = ['type' => 'success', 'message' => 'Stream stopped'];
}

if (isset($_GET['restart'])) {
    stop_stream($_GET['restart']);
    usleep(100000);
    start_stream($_GET['restart']);
    $message = ['type' => 'success', 'message' => 'Stream restarted succesfully'];
}

if (isset($_GET['delete'])) {
    Stream::find($_GET['delete'])->delete();
    $message = ['type' => 'success', 'message' => 'Stream deleted'];
}

/**
 * CONTROL DEL WATCHER (cron interno)
 */
if (isset($_POST['start_cron'])) {
    $setting->enableCheck = "1";
    $setting->save();
    exec('/usr/bin/sudo /usr/bin/systemctl start streamtool-watcher');
    $message = ['type' => 'success', 'message' => "Stream process watcher started"];
}

if (isset($_POST['stop_cron'])) {
    $setting->enableCheck = "0";
    $setting->save();
    exec('/usr/bin/sudo /usr/bin/systemctl stop streamtool-watcher');
    usleep(500000);
    $message = ['type' => 'error', 'message' => "Stream process watcher stopped"];
}

/**
 * ACCIONES MASIVAS EXISTENTES: DELETE / START / STOP
 */
if (isset($_POST['mass_delete']) && isset($_POST['mselect'])) {
    foreach ($_POST['mselect'] as $streamids) {
        Stream::find($streamids)->delete();
    }
    $message = ['type' => 'success', 'message' => 'Streams deleted'];
}

if (isset($_POST['mass_start']) && isset($_POST['mselect'])) {
    foreach ($_POST['mselect'] as $streamids) {
        start_stream($streamids);
    }
    $message = ['type' => 'success', 'message' => 'Streams started'];
}

if (isset($_POST['mass_stop']) && isset($_POST['mselect'])) {
    foreach ($_POST['mselect'] as $streamids) {
        stop_stream($streamids);
    }
    $message = ['type' => 'success', 'message' => 'Streams stopped'];
}

/**
 * NUEVO: MASS EDIT
 * Permite cambiar categoría y perfil de transcode para todos los streams seleccionados.
 */
if (isset($_POST['mass_edit_apply']) && isset($_POST['mselect'])) {
    $newCategoryId  = !empty($_POST['mass_edit_category'])  ? (int)$_POST['mass_edit_category']  : null;
    $newTranscodeId = !empty($_POST['mass_edit_transcode']) ? (int)$_POST['mass_edit_transcode'] : null;

    // Si no se ha elegido nada, no hacemos nada
    if ($newCategoryId || $newTranscodeId) {

        foreach ($_POST['mselect'] as $streamId) {
            $stream = Stream::find($streamId);
            if (!$stream) {
                continue;
            }

            // Detectar dinámicamente cómo se llama el campo de categoría
            if (!empty($newCategoryId)) {
                if (array_key_exists('category_id', $stream->getAttributes())) {
                    $stream->category_id = $newCategoryId;
                } elseif (array_key_exists('category', $stream->getAttributes())) {
                    $stream->category = $newCategoryId;
                } elseif (array_key_exists('categoryId', $stream->getAttributes())) {
                    $stream->categoryId = $newCategoryId;
                }
            }

            // Detectar dinámicamente cómo se llama el campo de transcode
            if (!empty($newTranscodeId)) {
                if (array_key_exists('trans_id', $stream->getAttributes())) {
                    $stream->trans_id = $newTranscodeId;
                } elseif (array_key_exists('transcode_id', $stream->getAttributes())) {
                    $stream->transcode_id = $newTranscodeId;
                } elseif (array_key_exists('transId', $stream->getAttributes())) {
                    $stream->transId = $newTranscodeId;
                }
            }

            $stream->save();
        }

        $message = ['type' => 'success', 'message' => 'Streams updated'];
    }
}

/**
 * FILTRO DE LISTA (running / stopped / todos)
 */
if (isset($_GET['running']) && $_GET['running'] == 1) {
    $title  = "Running Streams";
    $stream = Stream::where('status', '=', 1)->get();
} else if (isset($_GET['running']) && $_GET['running'] == 2) {
    $title  = "Stopped Streams";
    $stream = Stream::where('status', '=', 2)->get();
} else {
    $stream = Stream::all();
}

/**
 * ESTADO DEL CRON Y CARGA DE DATOS ADICIONALES PARA LA VISTA
 */
$cronStatus = shell_exec('ps faux | grep "/[o]pt/streamtool/app/php/bin/php /opt/streamtool/app/www/cron.php" > /dev/null; echo $?') == 0 ? 1 : 0;

// Categorías y perfiles de transcode para el MASS EDIT
$categories = Category::all();
$transcodes = Transcode::all();

/**
 * RENDER DE LA VISTA streams.blade.php
 */
echo $template->view()->make('streams')
    ->with('streams', $stream)
    ->with('message', $message)
    ->with('title', $title)
    ->with('cronStatus', $cronStatus)
    ->with('setting', Setting::first())
    ->with('categories', $categories)
    ->with('transcodes', $transcodes)
    ->render();
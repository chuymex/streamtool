@extends('main')
@section('content')

<div class="">
    <div class="row">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="x_panel">
                <div class="x_title">
                    <h2>{{ $title }} </h2>
                    <ul class="nav navbar-right panel_toolbox">
                        <a class="btn btn-round btn-primary btn-sm" href="manage_stream.php" title="Add">
                            Add stream
                        </a>
                    </ul>
                    <div class="clearfix"></div>
                </div>

                {{-- FORMULARIO PRINCIPAL: acciones masivas + listado --}}
                <form action="" method="post">
                    {{-- Acciones masivas existentes --}}
                    <button type="submit" name="mass_start" value="Mass start" class="btn btn-sm btn-success" onclick="return confirm('Mass start ?')">
                        <i class="far fa-play-circle"></i> MASS START
                    </button>
                    <button type="submit" name="mass_stop" value="Mass stop" class="btn btn-sm btn-danger" onclick="return confirm('Mass stop ?')">
                        <i class="far fa-stop-circle"></i> MASS STOP
                    </button>
                    <button type="submit" name="mass_delete" value="Mass delete" class="btn btn-sm btn-danger" onclick="return confirm('Mass delete ?')">
                        <i class="far fa-times-circle"></i> MASS DELETE
                    </button>

                    {{-- Botón que muestra/oculta el panel de MASS EDIT --}}
                    <button type="button" id="btn-mass-edit-toggle" class="btn btn-sm btn-info">
                        <i class="far fa-edit"></i> MASS EDIT
                    </button>

                    {{-- Control del watcher de procesos --}}
                    @if($cronStatus == 1)
                        <button style="float: right;" type="submit" name="stop_cron" value="Stop stream watcher" class="btn btn-sm btn-warning">
                            <i class="fas fa-hand-paper"></i> Stop stream watcher
                        </button>
                    @else
                        <button style="float: right;" type="submit" name="start_cron" value="Start stream watcher" class="btn btn-sm btn-success">
                            <i class="fas fa-play"></i>| Start stream watcher
                        </button>
                    @endif

                    @if(count($streams) > 0)

                        {{-- Mensaje de feedback --}}
                        @if($message)
                            <div class="alert alert-{{ $message['type'] }}">
                                {{ $message['message'] }}
                            </div>
                        @endif

                        {{-- PANEL DE MASS EDIT --}}
                        <div id="mass-edit-panel" class="well" style="margin-top:10px; display:none;">
                            <h4>Mass edit selected streams</h4>
                            <p>Selecciona la nueva categoría y/o perfil de transcode para aplicar a todos los streams marcados.</p>
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="mass_edit_category">Category</label>
                                    <select name="mass_edit_category" id="mass_edit_category" class="form-control">
                                        <option value="">-- no change --</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="mass_edit_transcode">Transcode profile</label>
                                    <select name="mass_edit_transcode" id="mass_edit_transcode" class="form-control">
                                        <option value="">-- no change --</option>
                                        @foreach($transcodes as $tr)
                                            <option value="{{ $tr->id }}">{{ $tr->name ?? ('ID '.$tr->id) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4" style="margin-top:24px;">
                                    <button type="submit" name="mass_edit_apply" value="Mass edit apply" class="btn btn-sm btn-primary" onclick="return confirm('Apply changes to selected streams?')">
                                        <i class="far fa-save"></i> Apply to selected
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- TABLA DE STREAMS --}}
                        <div class="">
                            <table id="example" class="table table-striped responsive-utilities jambo_table bulk_action table-condensed" style="font-family: Work Sans,sans-serif;">
                                <thead>
                                    <tr class="headings" style="font-family: Work Sans,sans-serif;">
                                        <th class="bulk_action">
                                            <input type="checkbox" id="check-all" class="tableflat">
                                        </th>
                                        <th>Name</th>
                                        <th>Status</th>
                                        <th>Category</th>
                                        <th>Input Codecs</th>
                                        <th>Output Encoders</th>
                                        <th>Uptime</th>
                                        <th class=" no-link last"><span class="nobr">Control</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($streams as $key => $stream)
                                        <tr>
                                            {{-- Checkbox por stream --}}
                                            <td class="center bulk_action">
                                                <input type="checkbox" class="tableflat check" value="{{ $stream->id }}" name="mselect[]">
                                            </td>

                                            {{-- Nombre --}}
                                            <td style="font-family: Work Sans,sans-serif;">
                                                {{ strtoupper($stream->name) }}
                                            </td>

                                            {{-- Estado + URL usada --}}
                                            <td class="center">
                                                <span class="label label-{{ $stream->status_label['label'] }}">
                                                    <i class="{{ $stream->status_label['icon'] }}"></i> {{ $stream->status_label['text'] }}
                                                </span>
                                                @if($stream->checker == 0)
                                                    <span class="label label-info"><i class="fas fa-check-circle"></i> Primary URL</span>
                                                @endif
                                                @if($stream->checker == 2)
                                                    <span class="label label-info"><i class="fas fa-exclamation-circle"></i> >Backup URL 1</span>
                                                @endif
                                                @if($stream->checker == 3)
                                                    <span class="label label-info"><i class="fas fa-exclamation-circle"></i> Backup URL 2</span>
                                                @endif
                                            </td>

                                            {{-- Categoría actual --}}
                                            <td class="center">
                                                <a color="purple" class="label label-default">
                                                    {{ $stream->category ? $stream->category->name : '' }}
                                                </a>
                                            </td>

                                            {{-- Codecs de entrada --}}
                                            <td style="font-family: monospace;" class="center">
                                                <a class="label label-default">
                                                    <i class="fas fa-video"></i>
                                                    @if($stream->video_codec_name)
                                                        {{ strtoupper($stream->video_codec_name) }}
                                                    @else
                                                        N/A
                                                    @endif
                                                    <i class="fas fa-long-arrow-alt-down"></i>
                                                    <i class="fas fa-volume-up"></i>
                                                    @if($stream->audio_codec_name)
                                                        {{ strtoupper($stream->audio_codec_name) }}
                                                    @else
                                                        N/A
                                                    @endif
                                                </a>
                                            </td>

                                            {{-- Codecs de salida (transcode) --}}
                                            <td class="center" style="font-family: monospace;">
                                                <a class="label label-default">
                                                    <i class="fas fa-video"></i>
                                                    @if(($stream->transcode)->video_codec)
                                                        {{ strtoupper(($stream->transcode)->video_codec) }}
                                                    @else
                                                        COPY
                                                    @endif
                                                    <i class="fas fa-long-arrow-alt-up"></i>
                                                    <i class="fas fa-volume-up"></i>
                                                    @if(($stream->transcode)->audio_codec)
                                                        {{ strtoupper(($stream->transcode)->audio_codec) }}
                                                    @else
                                                        COPY
                                                    @endif
                                                </a>
                                            </td>

                                            {{-- COLUMNA UPTIME --}}
                                            <td class="center">
                                                @if($stream->duration > 0)
                                                    @if($stream->status_label["text"] == "RUNNING")
                                                        <a style="color: DarkGreen;"><i class="fa fa-clock fa-xs"></i></a>
                                                        <a>{{ secondsToTime($stream->duration) }}</a>
                                                        <a style="color: DarkGreen;"><i class="fas fa-wave-square fa-xs"></i></a> {{ $stream->fps }} fps
                                                    @else
                                                        <a style="color: DarkRed;">
                                                            <i class="fas fa-exclamation-circle fa-xs"></i>
                                                            {{ secondsToTime($stream->duration) }}
                                                        </a>
                                                    @endif
                                                @else
                                                    @if($stream->status_label["text"] == "RUNNING")
                                                        @if($stream->running && $stream->status != 2 && !empty($stream->uptime_started_at))
                                                            {{ format_uptime($stream->uptime_started_at) }}
                                                        @else
                                                            <span style="color: DarkGreen;">
                                                                <i class="fas fa-spinner fa-spin fa-xs"></i> analyzing
                                                            </span>
                                                        @endif
                                                    @else
                                                        <a style="color: DarkSlateGray;">
                                                            <i class="fas fa-stop-circle fa-xs"></i>
                                                        </a>
                                                    @endif
                                                @endif
                                            </td>

                                            {{-- COLUMNA CONTROL --}}
                                            <td class="center">
                                                <a class="btn-success btn-sm" title="START STREAM" href="streams.php?start={{ $stream->id }}"><i class="fas fa-play"></i></a>
                                                <a class="btn-danger btn-sm" title="STOP STREAM" href="streams.php?stop={{ $stream->id }}"><i class="fas fa-stop"></i></a>
                                                <a class="btn-warning btn-sm" title="RESTART STREAM" href="streams.php?restart={{ $stream->id }}"><i class="fas fa-redo-alt"></i></a>
                                                <a class="btn-info btn-sm" href="manage_stream.php?id={{ $stream->id }}" title="Edit"><i class="far fa-edit"></i></a>
                                                <a class="btn-danger btn-sm" href="streams.php?delete={{ $stream->id }}" title="Delete" onclick="return confirm('Delete {{ $stream->name }} ?')"><i class="far fa-trash-alt"></i></a>

                                                {{-- Botón para abrir reproductor interno (mismo tamaño que los demás) --}}
                                                <button type="button"
                                                        class="btn btn-info btn-sm btn-open-player"
                                                        title="Preview stream"
                                                        data-stream-id="{{ $stream->id }}"
                                                        data-stream-name="{{ strtoupper($stream->name) }}">
                                                    <i class="fas fa-tv"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="alert alert-info">
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                                No streams found
                            </div>
                        @endif
                </form>
            </div>
        </div>
    </div>
</div>

{{-- MODAL DEL REPRODUCTOR INTERNO (más pequeño y estético) --}}
<div class="modal fade" id="playerModal" tabindex="-1" role="dialog" aria-labelledby="playerModalLabel">
    <div class="modal-dialog modal-md" role="document" style="max-width: 720px;">
        <div class="modal-content" style="border-radius:6px; overflow:hidden;">
            <div class="modal-header" style="background:#2A3F54; color:#fff; border-bottom:none; padding:10px 15px;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff; opacity:0.8;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="playerModalLabel" style="font-size:14px; margin:0;">Stream player</h4>
            </div>
            <div class="modal-body" style="background-color:#000; padding:8px;">
                {{-- Contenedor con proporción 16:9 --}}
                <div style="position:relative; width:100%; padding-top:56.25%; background:#000;">
                    <video id="streamPlayer"
                           controls
                           autoplay
                           style="position:absolute; top:0; left:0; width:100%; height:100%; background-color:#000;"></video>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection


@section('js')
    <!-- Datatables -->
    <script src="js/datatables/js/jquery.dataTables.js"></script>
    <script src="js/datatables/tools/js/dataTables.tableTools.js"></script>
    <!-- HLS.js -->
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    {{-- Asegúrate de que icheck.min.js se carga en el layout principal --}}

    <script>
        var asInitVals = new Array();
        var check_state = '';
        var hls = null; // instancia global para hls.js

        $(document).ready(function() {

            // DataTables
            var oTable = $('#example').dataTable({
                "bDestroy": true,
                "oLanguage": {
                    "sSearch": "Search all columns:"
                },
                "aoColumnDefs": [{
                        'bSortable': false,
                        'aTargets': [0]
                    }
                ],
                'iDisplayLength': 30,
                "sPaginationType": "full_numbers"
            });

            // iCheck
            if ($.fn.iCheck) {
                $('input.tableflat').iCheck({
                    checkboxClass: 'icheckbox_flat-green',
                    radioClass: 'iradio_flat-green'
                });
            }

            // MASS EDIT toggle
            $('#btn-mass-edit-toggle').off('click').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('#mass-edit-panel').stop(true, true).slideToggle(200);
            });

            // Filtros de pie (si existen)
            $("tfoot input").keyup(function() {
                oTable.fnFilter(this.value, $("tfoot th").index($(this).parent()));
            });
            $("tfoot input").each(function(i) {
                asInitVals[i] = this.value;
            });
            $("tfoot input").focus(function() {
                if (this.className == "search_init") {
                    this.className = "";
                    this.value = "";
                }
            });
            $("tfoot input").blur(function(i) {
                if (this.value == "") {
                    this.className = "search_init";
                    this.value = asInitVals[$("tfoot input").index(this)];
                }
            });

            // Selección masiva con iCheck
            $('table input').on('ifChecked', function() {
                check_state = '';
                $(this).closest('tr').addClass('selected');
                countChecked();
            });
            $('table input').on('ifUnchecked', function() {
                check_state = '';
                $(this).closest('tr').removeClass('selected');
                countChecked();
            });

            $('.bulk_action input').on('ifChecked', function() {
                check_state = '';
                $(this).closest('tr').addClass('selected');
                countChecked();
            });
            $('.bulk_action input').on('ifUnchecked', function() {
                check_state = '';
                $(this).closest('tr').removeClass('selected');
                countChecked();
            });

            $('.bulk_action input#check-all').on('ifChecked', function() {
                check_state = 'check_all';
                countChecked();
            });
            $('.bulk_action input#check-all').on('ifUnchecked', function() {
                check_state = 'uncheck_all';
                countChecked();
            });

            // Reproductor interno: botón por fila
            $('.btn-open-player').on('click', function() {
                var streamId   = $(this).data('stream-id');
                var streamName = $(this).data('stream-name');

                // AJUSTA ESTA RUTA según usas en VLC (ejemplo: /hls/{id}_.m3u8)
                var streamUrl = '/hls/' + streamId + '_.m3u8';

                $('#playerModalLabel').text('Stream player - ' + streamName);

                var video = document.getElementById('streamPlayer');

                // Limpia cualquier instancia previa
                if (hls) {
                    hls.destroy();
                    hls = null;
                }
                video.pause();
                video.removeAttribute('src');
                video.load();

                if (Hls.isSupported()) {
                    hls = new Hls();
                    hls.loadSource(streamUrl);
                    hls.attachMedia(video);
                    hls.on(Hls.Events.MANIFEST_PARSED, function() {
                        video.play();
                    });
                } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                    video.src = streamUrl;
                    video.addEventListener('loadedmetadata', function() {
                        video.play();
                    });
                } else {
                    alert('Este navegador no soporta HLS.');
                    return;
                }

                $('#playerModal').modal('show');
            });

            // Parar el video al cerrar el modal
            $('#playerModal').on('hidden.bs.modal', function () {
                var video = document.getElementById('streamPlayer');
                if (hls) {
                    hls.destroy();
                    hls = null;
                }
                video.pause();
                video.removeAttribute('src');
                video.load();
            });
        });

        // Cuenta y aplica selección masiva
        function countChecked() {
            if (check_state == 'check_all') {
                $(".bulk_action input[name='mselect[]']").iCheck('check');
            }
            if (check_state == 'uncheck_all') {
                $(".bulk_action input[name='mselect[]']").iCheck('uncheck');
            }

            var n = $(".bulk_action input[name='mselect[]']:checked").length;
            if (n > 0) {
                $('.column-title').hide();
                $('.bulk-actions').show();
                $('.action-cnt').html(n + ' Records Selected');
            } else {
                $('.column-title').show();
                $('.bulk-actions').hide();
            }
        }
    </script>
@endsection
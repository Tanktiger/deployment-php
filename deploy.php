<?php
/**
 * Idee: Lade jede angegebene Datei - ersetze die bestimmt gekennzeichneten Werte mit REGEX und speichere die Datei neu
 * Führe danach Composer aus wenn in der Config angegeben Wurde, das Composer verwendet werden soll
 */
if (isset($_SERVER['argv'][1])) {
    $dest = $_SERVER['argv'][1];
    $useServer = (isset($_SERVER['argv'][2]) && $_SERVER['argv'][2] == 'remote')? true:false;
    switch ($dest) {
        case 'local':
            deployLocal($useServer);
            break;
        case 'test':
            deployTest($useServer);
            break;
        case 'live':
            deployLive($useServer);
            break;
        default:
            echo PHP_EOL . 'Keine richtiges Ziel angegeben' . PHP_EOL;
    }
} else {
    echo PHP_EOL . 'Bitte Ziel angeben' . PHP_EOL;
}
exit('Fertig!');

function deployLocal($useServer) {
    $config = null;
    if (file_exists('configuration.local.php')) {
        include 'configuration.local.php';
        deployment ($config, $useServer);
    } else {
        echo PHP_EOL . 'Es fehlt die configuration.local.php' . PHP_EOL;
    }
}

function deployTest($useServer) {
    $config = null;
    if (file_exists('configuration.test.php')) {
        include 'configuration.test.php';
        deployment ($config, $useServer);
    } else {
        echo PHP_EOL . 'Es fehlt die configuration.test.php' . PHP_EOL;
    }
}

function deployLive($useServer) {
    $config = null;
    if (file_exists('configuration.live.php')) {
        include 'configuration.live.php';
        deployment ($config, $useServer);
    } else {
        echo PHP_EOL . 'Es fehlt die configuration.live.php' . PHP_EOL;
    }
}

function deployment ($config, $useServer) {
    $connection = null;
    $projectRootDir = dirname(__DIR__);
    if (isset($config['projectRootDir']) && !empty($config['projectRootDir'])) {
        $projectRootDir = $config['projectRootDir'];
    }
    if ($useServer) {
        $connection = ssh2_connect($config['server']['host'], $config['server']['port']);

        if (ssh2_auth_password($connection, $config['server']['user'], $config['server']['password'])) {
            echo PHP_EOL . "Authentication Successful!" . PHP_EOL;
        } else {
            exit(PHP_EOL .'SSH Authentication schlug fehl!' . PHP_EOL);
        }
    }

    //Replace .dist files
    if (isset($config['replaceFiles']) && !empty($config['replaceFiles'])) {
        foreach ($config['replaceFiles'] as $files) {
            if (isset($files['oldName']) && isset($files['newName'])) {
                $result = false;
                $oldFile = $projectRootDir . '/' . $files['oldName'];
                $newFile = $projectRootDir . '/' . $files['newName'];
                if ($useServer && $connection ) {
                    $result = ssh2_exec($connection, 'cp ' . $oldFile . ' ' . $newFile);
                    if (!$result) {
                        echo PHP_EOL . 'Kopieren der Datei schlug fehl: ' . $oldFile . PHP_EOL;
                    } else {
                        foreach ($files['variables'] as $old => $new) {
                            $cmd = "sed -i 's/" . $old . "/" . $new . "/g' " . $newFile;
                            if (!ssh2_exec($connection, $cmd)) {
                                echo PHP_EOL . 'Konnte Variable: ' . $old . ' nicht auf dem Server in Datei ersetzen: ' . $newFile  . PHP_EOL;
                            }
                        }
                    }
                } else {
                    $contents = file_get_contents($oldFile);
                    foreach ($files['variables'] as $old => $new) {
                        if (!preg_replace('/'.$old.'/',$new,$contents)) {
                            echo PHP_EOL . 'Konnte Variable: ' . $old . ' nicht ersetzen in Datei: ' . $oldFile  . PHP_EOL;
                        }
                    }
                    if (!file_put_contents($newFile, $contents)) {
                        echo PHP_EOL . 'Konnte ' . $newFile . ' nicht schreiben!' . PHP_EOL;
                    }
                }
            } else {
                echo PHP_EOL . 'Neuer und alter Dateiname müssen gesetzt sein!' . PHP_EOL;
            }
        }
    }

    //execute Composer
    if (isset($config['composer']) && $config['composer'] && !empty($config['composer'])) {
        $cmd = 'php ' . $projectRootDir . '/composer.phar update';
        if ($useServer && $connection) {
            if (!ssh2_exec($connection, $cmd)) {
                echo PHP_EOL . 'Konnte Composer nicht auf dem Server ausführen!'  . PHP_EOL;
            }
        } else {
            if (!system($cmd)) {
                echo PHP_EOL . 'Konnte Composer nicht ausführen!'  . PHP_EOL;
            }
        }
    }

    //set directory and file permissions
    if (isset($config['permissions'])) {
        foreach ($config['permissions'] as $file => $mode) {
            $cmd = 'chmod ' . $mode . ' ' . $projectRootDir . '/' . $file;
            if ($useServer && $connection) {
                if (!ssh2_exec($connection, $cmd)) {
                    echo PHP_EOL . 'Konnte Rechte nicht auf dem Server setzen: ' . $file  . PHP_EOL;
                }
            } else {
                if (!system($cmd)) {
                    echo PHP_EOL . 'Konnte Rechte nicht setzen: ' . $file  . PHP_EOL;
                }
            }
        }
    }
    //doctrine
    if (isset($config['doctrine']) && $config['doctrine']['update']) {
        $cmdLinux = $projectRootDir . '/' . $config['doctrine']['cmd_linux'] . ' orm:schema-tool:update --complete';
        $cmdWin = $projectRootDir . '/' . $config['doctrine']['cmd_windows'] . ' orm:schema-tool:update --complete';
        if ($useServer && $connection) {
            if (!ssh2_exec($connection, $cmdLinux)) {
                echo PHP_EOL . 'Konnte Doctrine nicht auf dem Server ausführen!' . PHP_EOL;
            }
        } else {
            $result = false;
            switch (true) {
                case stristr(PHP_OS, 'DAR'):
                    $result = system($cmdLinux);
                    break;
                case stristr(PHP_OS, 'WIN'):
                    $result = system($cmdWin);
                    break;
                case stristr(PHP_OS, 'LINUX'):
                    $result = system($cmdLinux);
                    break;
                default : break;
            }
            if (!$result) {
                echo PHP_EOL . 'Konnte Doctrine nicht ausführen!' . PHP_EOL;
            }
        }
    }
}

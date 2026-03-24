<?php

namespace Autodeployer;

class Notifier
{
    /**
     * Собирает важные системные предупреждения для вывода в интерфейсе
     */
    public function getEnvironmentAlerts(GitClient $git): array
    {
        $alerts = [];
        $uncommittedFiles = $git->getUncommittedFiles();
        if (!empty($uncommittedFiles)) {
            $count = count($uncommittedFiles);
            $previewFiles = array_slice($uncommittedFiles, 0, 3);
            $filesList = implode('<br> &bull; ', $previewFiles);

            if ($count > 3) {
                $filesList .= "<br> &bull; <i>и еще " . ($count - 3) . " файл(ов)...</i>";
            }

            $alerts[] = [
                'type' => 'warning',
                'title' => "На сервере есть незакомиченные изменения ({$count})!",
                'message' => "Кто-то правил файлы прямо на площадке. При деплое они будут сохранены (commit) или уничтожены (reset).<br><br><b>Изменены:</b><br> &bull; {$filesList}"
            ];
        }

        return $alerts;
    }
}
@echo off
rem This script copies specific file types from a source directory and all its subdirectories
rem to a single destination directory, overwriting existing files.

set "source_dir=C:\xampp\htdocs\wordpress\wp-content\plugins\KAMAL"
set "dest_dir=F:\Client Works\Kamal\Working Demo\files"

echo Starting file copy from all subfolders of %source_dir% to %dest_dir%...
echo.

rem The FOR /R loop iterates through all files in the source directory and subdirectories.
rem The %%f variable holds the full path to each file found.
rem The COPY command copies each file to the destination, using /Y to automatically overwrite.
rem This approach copies only the files, not the folder structure.

for /R "%source_dir%" %%f in (*.js *.php *.css) do (
    echo Copying "%%f" to "%dest_dir%"
    copy "%%f" "%dest_dir%" /Y
)

echo.
echo File copy complete!
pause

@echo off
IF NOT EXIST media-autobuild_suite-master\media-autobuild_suite.bat powershell -Command Start-BitsTransfer -Source "https://github.com/m-ab-s/media-autobuild_suite/archive/master.zip" -Destination "media-autobuild_suite.zip"
IF NOT EXIST media-autobuild_suite-master\media-autobuild_suite.bat powershell -Command Start-BitsTransfer -Source "https://github.com/m-ab-s/media-autobuild_suite/archive/master.zip" -Destination "media-autobuild_suite.zip"
IF EXIST media-autobuild_suite.zip powershell Expand-Archive media-autobuild_suite.zip -DestinationPath .
IF EXIST media-autobuild_suite.zip del media-autobuild_suite.zip
IF NOT EXIST media-autobuild_suite-master echo Download failed
cd media-autobuild_suite-master
..\php\php.exe ..\ndi_patch_shell.php
media-autobuild_suite.bat
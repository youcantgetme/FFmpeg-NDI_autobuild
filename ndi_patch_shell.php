<?php
//remove update , uncomment media_suite_update_sh() to cancel update
error_reporting(E_ALL);
copy_ndi_file();
media_autobuild_suite_bat();
mediasuitehelper_sh();
media_suite_compile_sh();
//media_suite_update_sh()
echo 'Newtek Patching completed'.PHP_EOL;

function copy_ndi_file()
{
	exec('xcopy /e /y ..\ndi_patch\NDI40SDK\* local64\NDI40SDK\*');
	exec('xcopy /e /y ..\php\* php\*');
	exec('copy /y ..\ndi_patch_git.php php\ndi_patch_git.php');
	if(!is_dir('msys64\mingw64\lib'))mkdir('msys64\mingw64\lib',0777,true);
	exec('copy /y ..\ndi_patch\NDI40SDK\Lib\x64\Processing.NDI.Lib.x64.lib msys64\mingw64\lib\ndi.lib');
}
function media_autobuild_suite_bat()
{
	$c=file_get_contents('media-autobuild_suite.bat');
	$c=str_replace('librtmp ', 'libndi_newtek ',$c); //librtmp cause disconnect
	$c=str_replace(' libopenh264', ' #libopenh264',$c); //libopenh264 , cisco h264 lib forced shared due to license
	$c=str_replace('if not exist %build%\ffmpeg_options.txt', 'if 1==1',$c);
	$c=replace_in_range($c,'FFmpeg options','FFmpeg options','pause','');
	file_put_contents('media-autobuild_suite.bat', $c);
}
function mediasuitehelper_sh()
{
	/*within range of 10 lines , remove ndi-newtek text from disabled section
	=====
	    enabled_any lib{vo-aacenc,aacplus,utvideo,dcadec,faac,ebur128,ndi_newtek,ndi-newtek} netcdf &&
        do_removeOption "--enable-(lib(vo-aacenc|aacplus|utvideo|dcadec|faac|ebur128|ndi_newtek|ndi-newtek)|netcdf)" &&
        sed -ri 's;--enable-(lib(vo-aacenc|aacplus|utvideo|dcadec|faac|ebur128|ndi_newtek|ndi-newtek)|netcdf);;g' \
	=====
	*/
	$line_limit=10;
	$remove=[
		',ndi_newtek',
		',ndi-newtek',
		'|ndi_newtek',
		'|ndi-newtek',
		'ndi-newtek',
		'ndi_newtek',
	];
	$c=file_get_contents('build\media-suite_helper.sh');
	
	$c=explode("\n",$c);

	$result='';
	foreach($c as $line => $o)
	{
		if(strpos($o,'enabled_any') && strpos($o,'ndi_newtek'))
		{
			$result.=
'    if enabled libndi_newtek; then
        do_removeOption --enable-libndi_newtek
        do_addOption --enable-libndi-newtek
    fi'."\n\n";
		}
		$result.=$o."\n";
	}
	$data=replace_in_range($result,'enabled_any','ndi_newtek',$remove,'',5);
	file_put_contents('build\media-suite_helper.sh',$data);
}

function media_suite_compile_sh()
{
	$data=file_get_contents('build\media-suite_compile.sh');
		
	//part.1
	if(strpos($data,'local64/NDI')===false)
	{
		$tmp=explode('do_print_progress "Compiling ${bold}static${reset} FFmpeg"',$data);
		$tmp[1]=str_replace('CFLAGS="${ffmpeg_cflags:-$CFLAGS}"','CFLAGS="${ffmpeg_cflags:-$CFLAGS} -I$LOCALDESTDIR/local64/NDI40SDK/Include"',$tmp[1]);
		$tmp[1]=str_replace_first('LDFLAGS+=" -L$LOCALDESTDIR/lib -L$MINGW_PREFIX/lib"','LDFLAGS+=" -L$LOCALDESTDIR/lib -L$MINGW_PREFIX/lib -L$LOCALDESTDIR/local64/NDI40SDK/Include"',$tmp[1]);
		$data=$tmp[0].'do_print_progress "Compiling ${bold}static${reset} FFmpeg"'.$tmp[1];
	}

	//part.2
	$c=explode("\n",$data);
	$line=0;
	$found=false;
	foreach($c as $o)
	{
		$line++;
		if(strpos($o,'_check=(AMF/core/Version.h)')!==false)
		{
			$found=true;
			break;
		}
	}
	if($found && strpos($data,'Processing.NDI.Lib')===false)
	{
		$data=put_at_line($data,ndi_script_in_media_suite_compile_sh(),$line-1);
	}

	//part.3
	if(strpos($data,'php/php.exe php/ndi_patch_git.php')===false)
	{
		$data=str_replace('_uninstall=(include/libav{codec,device,filter,format,util,resample}','cd_safe /trunk/'.PHP_EOL.'php/php.exe php/ndi_patch_git.php'.PHP_EOL.'cd_safe /build/ffmpeg-git'.PHP_EOL.'_uninstall=(include/libav{codec,device,filter,format,util,resample}',$data);
	}
	file_put_contents('build\media-suite_compile.sh', $data);
	
	return false;
}

function media_suite_update_sh()
{
	$c=file_get_contents('build\media-suite_update.sh');
	$data=put_at_line($c,'exit',1);
	file_put_contents('build\media-suite_update.sh',$data);
}

function put_at_line($haystack,$needle,$atline=false)
{
	if($atline===false)return false;
	$result='';
	$c=explode("\n",$haystack);
	foreach($c as $line => $o)
	{
		$result.=$o."\n";
		if($atline == $line+1)
		{
			$result.=$needle."\n";
		}
	}
	return $result;
}
function replace_in_range($haystack,$needle1,$needle2,$replace_from,$replace_to,$range_line=10)
{
	//if both "needle" and 'replace_from' exist , will trigger replace function for next 10 range_line
	$execute=false;
	$remain_lines=0;
	$result='';
	$c=explode("\n",$haystack);
	
	foreach($c as $line => $o)
	{
		$tmp=$o;

		if(strpos($o,$needle1) && strpos($o,$needle2))
		{
			$execute=true;
			$remain_lines=$range_line;
		}

		if($remain_lines > 0)
		{
			$tmp=str_replace($replace_from, $replace_to, $o);
			$remain_lines--;
		}
		$result.=$tmp."\n";
	}
	return $result;
}

function ndi_script_in_media_suite_compile_sh()
{
	return 
'if [[ $ffmpeg != "no" ]] && enabled_any libndi-newtek &&
    [[ -f "$LOCALDESTDIR/NDI40SDK/Include/Processing.NDI.Lib.h" ]]; then
    _includedir="$(cygpath -sm "$LOCALDESTDIR"/NDI40SDK/Include)"
    [[ $bits = 32bit ]] && _arch=x86 || _arch=x64
	
	echo $NDI_SDK_DIR
    echo -e "${green}Compiling ffmpeg with Newtek lib${reset}"
    echo -e "${orange}ffmpeg and apps that use it will depend on${reset}"
    echo -e "$(cygpath -m $LOCALDESTDIR/bin-video/Processing.NDI.Lib.${_arch}.dll) to run!${reset}"

    # if installed libndi.a is older than dll or main include file
    _check=(Processing.NDI.Lib.h libndi.a bin-video/Processing.NDI.Lib.${_arch}.dll)
    if test_newer installed "$LOCALDESTDIR/bin-video/Processing.NDI.Lib.${_arch}.dll" \
        "$_includedir/Processing.NDI.Lib.h" libndi.a || ! files_exist "${_check[@]}"; then
        mkdir -p "$LOCALBUILDDIR/newtek"
        pushd "$LOCALBUILDDIR/newtek" >/dev/null

        # install headers
        cmake -E copy_directory "$_includedir" "$LOCALDESTDIR/include"

        # fix ffmpeg breakage when compiling shared
        sed -i \'s|__declspec(dllexport)||g\' "$LOCALDESTDIR"/include/Processing.NDI.Lib.h

        # create import lib and install redistributable dll
        create_build_dir
        cp -f "$LOCALDESTDIR/NDI40SDK/Bin/x64/Processing.NDI.Lib.${_arch}.dll" .
        gendef - ./Processing.NDI.Lib.${_arch}.dll 2>/dev/null |
            sed -r -e \'s|^_||\' -e \'s|@[1-9]+$||\' > "libndi.def"
        dlltool -l "libndi.a" -d "libndi.def" \
            $([[ $bits = 32bit ]] && echo "-U") 2>/dev/null
        [[ -f libndi.a ]] && do_install "libndi.a"
        do_install ./Processing.NDI.Lib.${_arch}.dll bin-video/
        do_checkIfExist
        add_to_remove
        popd >/dev/null
    fi
    unset _arch _includedir
elif [[ $ffmpeg != "no" ]] && enabled libndi-newtek; then
    do_print_status "Newtek SDK" "$orange" "Not installed, disabling"
    do_removeOption --enable-libndi_newtek
fi'."\n";
}


function str_replace_first($from, $to, $content)
{
    $from = '/'.preg_quote($from, '/').'/';

    return preg_replace($from, $to, $content, 1);
}

?>

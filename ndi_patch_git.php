<?php
copy_ndi_file();
ffmpeg_doc_indevs_text();
ffmpeg_configure();
ffmpeg_makefile();
ffmpeg_alldevices_c();
ffmpeg_c();
cmdutils_c();
echo 'Newtek Patching completed'.PHP_EOL;

function copy_ndi_file()
{
	copy('..\ndi_patch\libndi_newtek_common.h','build\ffmpeg-git\libavdevice\libndi_newtek_common.h');
	copy('..\ndi_patch\libndi_newtek_dec.c','build\ffmpeg-git\libavdevice\libndi_newtek_dec.c');
	copy('..\ndi_patch\libndi_newtek_enc.c','build\ffmpeg-git\libavdevice\libndi_newtek_enc.c');
	if(!is_dir('local64\bin-video'))mkdir('local64\bin-video',0777,true);
	exec('copy /y ..\ndi_patch\NDI40SDK\Bin\x64\Processing.NDI.Lib.x64.dll local64\bin-video\Processing.NDI.Lib.x64.dll');
}
function cmdutils_c()
{
	$c=file_get_contents('build\ffmpeg-git\fftools\cmdutils.c');
	$c=str_replace('int hide_banner = 0;','int hide_banner = 1;',$c);
	$c=str_replace(
	'static void print_buildconf(int flags, int level)'."\n{\n".'    const char *indent = flags & INDENT ? "  " : "";',
	'static void print_buildconf(int flags, int level)'."\n{\n".'    return;'."\n".'    const char *indent = flags & INDENT ? "  " : "";',
	$c);
	$c=str_replace(
	'int idx = locate_option(argc, argv, options, "version");'."\n".'    if (hide_banner || idx)',
	'int idx = locate_option(argc, argv, options, "version");'."\n".'    av_log(NULL, AV_LOG_ERROR, "%s " FFMPEG_VERSION " \n", program_name);'."\n".'    if (hide_banner || idx)',
	$c);
	$c=file_put_contents('build\ffmpeg-git\fftools\cmdutils.c',$c);	
}
function ffmpeg_c()
{
	$c=file_get_contents('build\ffmpeg-git\fftools\ffmpeg_opt.c');
	$c=str_replace(
	'{'."\n".'    av_log(NULL, AV_LOG_INFO, "Hyper fast Audio and Video encoder\n");',
	'{'."\n".'    return;'."\n".'    av_log(NULL, AV_LOG_INFO, "Hyper fast Audio and Video encoder\n");',
	$c);
	$c=file_put_contents('build\ffmpeg-git\fftools\ffmpeg_opt.c',$c);
}

function ffmpeg_configure()
{
	$c=file_get_contents('build\ffmpeg-git\configure');
	/*
	 	--enable-decklink        enable Blackmagic DeckLink I/O support [no]
  	-	--enable-libndi_newtek   enable Newteck NDI I/O support [no]
  		--enable-mbedtls         enable mbedTLS, needed for https support
  	*/
  	$c=str_replace('--enable-decklink','--enable-libndi_newtek   enable Newteck NDI I/O support [no]'."\n".'  --enable-decklink',$c);
  	/*
  		EXTERNAL_LIBRARY_NONFREE_LIST="
    	decklink
    -	libndi_newtek
    	libfdk_aac
  	*/
  	$c=str_replace('decklink'."\n",'decklink'."\n".'    libndi_newtek'."\n",$c);
  	/*
  		decklink_outdev_extralibs="-lstdc++"
	-	libndi_newtek_indev_deps="libndi_newtek"
	-	libndi_newtek_indev_extralibs="-lndi"
	-	libndi_newtek_outdev_deps="libndi_newtek"
	-	libndi_newtek_outdev_extralibs="-lndi"
		dshow_indev_deps="IBaseFilter"
  	*/		
	$c=str_replace('decklink_deps_any="libdl LoadLibrary"','libndi_newtek_indev_deps="libndi_newtek"'."\n".'libndi_newtek_indev_extralibs="-lndi"'."\n".'libndi_newtek_outdev_deps="libndi_newtek"'."\n".'libndi_newtek_outdev_extralibs="-lndi"'."\n".'decklink_deps_any="libdl LoadLibrary"',$c);
	/*
		enabled decklink          && { require_headers DeckLinkAPI.h &&
                               { test_cpp_condition DeckLinkAPIVersion.h "BLACKMAGIC_DECKLINK_API_VERSION >= 0x0a090500" || die "ERROR: Decklink API version must be >= 10.9.5."; } }
	-	enabled libndi_newtek     && require_headers Processing.NDI.Lib.h
		enabled frei0r            && require_headers "frei0r.h dlfcn.h"
	*/
	$c=str_replace("\n".'enabled decklink',"\n".'enabled libndi_newtek     && require_headers Processing.NDI.Lib.h'."\n".'enabled decklink',$c);	
	file_put_contents('build\ffmpeg-git\configure', $c);
}
function ffmpeg_makefile()
{
	$c=file_get_contents('build\ffmpeg-git\libavdevice\Makefile');
/*
OBJS-$(CONFIG_DECKLINK_INDEV)            += decklink_dec.o decklink_dec_c.o decklink_common.o
-OBJS-$(CONFIG_LIBNDI_NEWTEK_OUTDEV)      += libndi_newtek_enc.o
-OBJS-$(CONFIG_LIBNDI_NEWTEK_INDEV)       += libndi_newtek_dec.o
OBJS-$(CONFIG_DSHOW_INDEV)               += dshow_crossbar.o dshow.o dshow_enummediatypes.o \
*/
	$c=str_replace('OBJS-$(CONFIG_DSHOW_INDEV)','OBJS-$(CONFIG_LIBNDI_NEWTEK_OUTDEV)      += libndi_newtek_enc.o'."\n".'OBJS-$(CONFIG_LIBNDI_NEWTEK_INDEV)       += libndi_newtek_dec.o'."\n".'OBJS-$(CONFIG_DSHOW_INDEV)',$c);
/*
SKIPHEADERS-$(CONFIG_DECKLINK)           += decklink_enc.h decklink_dec.h \
                                            decklink_common_c.h
-SKIPHEADERS-$(CONFIG_LIBNDI_NEWTEK_INDEV) += libndi_newtek_common.h
-SKIPHEADERS-$(CONFIG_LIBNDI_NEWTEK_OUTDEV) += libndi_newtek_common.h
SKIPHEADERS-$(CONFIG_DSHOW_INDEV)        += dshow_capture.h
*/
	$c=str_replace('SKIPHEADERS-$(CONFIG_DSHOW_INDEV)        += dshow_capture.h', 'SKIPHEADERS-$(CONFIG_LIBNDI_NEWTEK_INDEV) += libndi_newtek_common.h'."\n".'SKIPHEADERS-$(CONFIG_LIBNDI_NEWTEK_OUTDEV) += libndi_newtek_common.h'."\n".'SKIPHEADERS-$(CONFIG_DSHOW_INDEV)        += dshow_capture.h', $c);
	file_put_contents('build\ffmpeg-git\libavdevice\Makefile', $c);
}
function ffmpeg_alldevices_c()
{
	$c=file_get_contents('build\ffmpeg-git\libavdevice\alldevices.c');
/*
extern AVOutputFormat ff_decklink_muxer;
-extern AVInputFormat  ff_libndi_newtek_demuxer;
-extern AVOutputFormat ff_libndi_newtek_muxer;
extern AVInputFormat  ff_dshow_demuxer;
*/
	$c=str_replace('extern AVInputFormat  ff_dshow_demuxer;', 'extern AVInputFormat  ff_libndi_newtek_demuxer;'."\n".'extern AVOutputFormat ff_libndi_newtek_muxer;'."\n".'extern AVInputFormat  ff_dshow_demuxer;', $c);
	file_put_contents('build\ffmpeg-git\libavdevice\alldevices.c', $c);
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

function ffmpeg_doc_outdevs_texi()
{
//See also @url{http://linux-fbdev.sourceforge.net/}, and fbset(1).

$text='@section libndi_newtek

The libndi_newtek output device provides playback capabilities for using NDI (Network
Device Interface, standard created by NewTek).

Output filename is a NDI name.

To enable this output device, you need the NDI SDK and you
need to configure with the appropriate @code{--extra-cflags}
and @code{--extra-ldflags}.

NDI uses uyvy422 pixel format natively, but also supports bgra, bgr0, rgba and
rgb0.

@subsection Options

@table @option

@item reference_level
The audio reference level in dB. This specifies how many dB above the
reference level (+4dBU) is the full range of 16 bit audio.
Defaults to @option{0}.

@item clock_video
These specify whether video "clock" themselves.
Defaults to @option{false}.

@item clock_audio
These specify whether audio "clock" themselves.
Defaults to @option{false}.

@end table

@subsection Examples

@itemize

@item
Play video clip:
@example
ffmpeg -i "udp://@@239.1.1.1:10480?fifo_size=1000000&overrun_nonfatal=1" -vf "scale=720:576,fps=fps=25,setdar=dar=16/9,format=pix_fmts=uyvy422" -f libndi_newtek NEW_NDI1
@end example

@end itemize';
//@section opengl
//OpenGL output device.

	$c=file_get_contents('build\ffmpeg-git\doc\outdevs.texi');
	$c=str_replace('@section opengl', $text."\n"."\n".'@section opengl',$c);
	file_put_contents('build\ffmpeg-git\doc\outdevs.texi',$c);
	//file_put_contents('outdevs.texi',$c);

}
function ffmpeg_doc_indevs_text()
{
//	Default is @code{qvga}.
//	@end table
	
$text='@section libndi_newtek

The libndi_newtek input device provides capture capabilities for using NDI (Network
Device Interface, standard created by NewTek).

Input filename is a NDI source name that could be found by sending -find_sources 1
to command line - it has no specific syntax but human-readable formatted.

To enable this input device, you need the NDI SDK and you
need to configure with the appropriate @code{--extra-cflags}
and @code{--extra-ldflags}.

@subsection Options

@table @option

@item find_sources
If set to @option{true}, print a list of found/available NDI sources and exit.
Defaults to @option{false}.

@item wait_sources
Override time to wait until the number of online sources have changed.
Defaults to @option{0.5}.

@item allow_video_fields
When this flag is @option{false}, all video that you receive will be progressive.
Defaults to @option{true}.

@item extra_ips
If is set to list of comma separated ip addresses, scan for sources not only
using mDNS but also use unicast ip addresses specified by this list.

@end table

@subsection Examples

@itemize

@item
List input devices:
@example
ffmpeg -f libndi_newtek -find_sources 1 -i dummy
@end example

@item
List local and remote input devices:
@example
ffmpeg -f libndi_newtek -extra_ips "192.168.10.10" -find_sources 1 -i dummy
@end example

@item
Restream to NDI:
@example
ffmpeg -f libndi_newtek -i "DEV-5.INTERNAL.M1STEREO.TV (NDI_SOURCE_NAME_1)" -f libndi_newtek -y NDI_SOURCE_NAME_2
@end example

@item
Restream remote NDI to local NDI:
@example
ffmpeg -f libndi_newtek -extra_ips "192.168.10.10" -i "DEV-5.REMOTE.M1STEREO.TV (NDI_SOURCE_NAME_1)" -f libndi_newtek -y NDI_SOURCE_NAME_2
@end example

@end itemize';
	
//	@section openal

	$c=file_get_contents('build\ffmpeg-git\doc\indevs.texi');
	$c=str_replace('@section openal', $text."\n"."\n".'@section openal',$c);
	file_put_contents('build\ffmpeg-git\doc\indevs.texi',$c);
}

function str_replace_first($from, $to, $content)
{
    $from = '/'.preg_quote($from, '/').'/';

    return preg_replace($from, $to, $content, 1);
}

?>

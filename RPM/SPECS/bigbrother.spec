%define _topdir		%(pwd)/RPM
%define name            org.bigbrothercctv.bigbrother
%define version		0.9


Summary:        bigbrothercctv.org BigBrother
License:        GPL
Name:           %{name}
Version:        %{version}
Release:        1
Source:         %{name}.tar.gz
Prefix:         /usr
Group:          System Environment/Daemons
BuildArch: 	noarch
Requires:	ffmpeg,python,bash

%description
BigBrother wrapper daemon for FFMPEG to provide CCTV recording & mirroring

%pre
getent group cctvwriters || groupadd cctvwriters
getent group cctvviewers || groupadd cctvviewers
getent passwd bigbrother || useradd -r -M -s /sbin/nologin -g cctvwriters bigbrother
 
%prep
%setup -c

%build
 
%install
mkdir $RPM_BUILD_ROOT/usr/
mkdir $RPM_BUILD_ROOT/usr/local/
mkdir $RPM_BUILD_ROOT/usr/local/bigbrother/
mkdir $RPM_BUILD_ROOT/etc
mkdir $RPM_BUILD_ROOT/etc/systemd
mkdir $RPM_BUILD_ROOT/etc/systemd/system
cp -r ./* $RPM_BUILD_ROOT/usr/local/bigbrother
cp ./bigbrotherd.service  $RPM_BUILD_ROOT/etc/systemd/system
chmod g+s $RPM_BUILD_ROOT/usr/local/bigbrother/mirrorwebroot

%post
systemctl daemon-reload

%files
%attr (755,bigbrother,cctvwriters) /usr/local/bigbrother/
%attr (644,bigbrother,cctvwriters) /usr/local/bigbrother/README.txt
%attr (644,bigbrother,cctvwriters) /usr/local/bigbrother/License.txt
%attr (750,bigbrother,cctvwriters) /usr/local/bigbrother/bblogger
%attr (660,bigbrother,cctvwriters) /usr/local/bigbrother/bigbrother.conf
%attr (660,bigbrother,cctvwriters) /usr/local/bigbrother/bigbrother_camera.conf
%attr (750,bigbrother,cctvwriters) /usr/local/bigbrother/bigbrotherd
%attr (750,bigbrother,cctvwriters) /usr/local/bigbrother/mirror_hls.sh
%attr (750,bigbrother,cctvwriters) /usr/local/bigbrother/record_bycamera.sh
%attr (750,bigbrother,cctvwriters) /usr/local/bigbrother/record_byday.sh
%attr (750,bigbrother,cctvwriters) /usr/local/bigbrother/extract.sh
%attr(750,bigbrother,cctvviewers) /usr/local/bigbrother/mirrorwebroot
%attr(640,bigbrother,cctvviewers) /usr/local/bigbrother/mirrorwebroot/*
%attr (644,root,root)/etc/systemd/system/bigbrotherd.service

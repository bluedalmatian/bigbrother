#Uses FPM to build debian package so you will need to run
#apt install ruby ruby-dev build-essential rpm
#gem install --no-document fpm
# to set up fpm first

STAGEDIR = /tmp/bigbrother-stage
INSTALLDIR = $(STAGEDIR)/usr/local/bigbrother
SYSTEMDDIR = $(STAGEDIR)/etc/systemd/system

VERSION = 2.4 #Used for Deb only, edit the +MANIFEST for FreeBSD version pkg and the RPM/SPECS/bigbrother.spec file for rpm version
DEPENDS = --depends ffmpeg --depends python3 \
          --depends "python3-opencv >= 4.10" --depends python3-zeep --depends python3-py7zr

FPMOPTIONS = --name org.bigbrothercctv.bigbrother \
             --version $(VERSION) \
             -a all \
             --category Video \
             --license 'GPLv3 for software and BigBrother AI Model License for AI model' \
             --maintainer 'bigbrothercctv.org' \
             $(DEPENDS)

default:
	echo "Run make deb|rpm|pkgng|clean"
	
installbinary:
	rm -rf $(STAGEDIR)
	mkdir -p $(INSTALLDIR)
	mkdir -p $(SYSTEMDDIR)
	cp ./*.sh  $(INSTALLDIR)
	cp ./bigbrotherd  $(INSTALLDIR)
	cp -R ./mirrorwebroot  $(INSTALLDIR)
	cp ./bblogger  $(INSTALLDIR)
	cp ./bigbrother.conf  $(INSTALLDIR)
	cp ./bigbrother_camera.conf  $(INSTALLDIR)
	cp ./bigbrother_event.conf  $(INSTALLDIR)
	cp ./bigbrother_control.conf  $(INSTALLDIR)
	cp ./bigbrotherd.service $(SYSTEMDDIR)
	cp ./LICENSE*  $(INSTALLDIR)
	cp ./README.txt  $(INSTALLDIR)
	cp ./markup_y5onnx.py $(INSTALLDIR)
	cp ./bbeventmonitor_ffy5onnx $(INSTALLDIR)
	cp ./bbcameracontrolshell $(INSTALLDIR)
	cp ./bbptzcameracontrollerd_onvif $(INSTALLDIR)
	cp -R ./onnx $(INSTALLDIR)
	cp -R ./octaquad $(INSTALLDIR)

#Use fpm to make a .deb
deb: installbinary
	rm -f org.bigbrothercctv.bigbrother*.deb
	fpm -s dir -t deb $(FPMOPTIONS) \
	  --prefix / \
	  --after-install postinstall-pak \
	  --before-remove preremove-pak \
	  -C $(STAGEDIR) \
	  usr/local/bigbrother \
	  etc/systemd/system/bigbrotherd.service
	set -e; \
	ORIGDIR=$$(pwd) ; \
	DEBFILE=$$(ls -t org.bigbrothercctv.bigbrother*.deb | head -n1) ; \
	echo "Stripping parent dirs from: $$DEBFILE" ; \
	WORKDIR=$$(mktemp -d) ; \
	cp "$$ORIGDIR/$$DEBFILE" "$$WORKDIR/pkg.deb" ; \
	cd "$$WORKDIR" ; \
	ar x pkg.deb ; \
	mkdir extracted ; \
	tar -xf data.tar.* -C extracted ; \
	rm -f data.tar.* ; \
	cd extracted ; \
	tar --no-recursion --owner=0 --group=0 --numeric-owner -cf ../data.tar \
       $$(find . -mindepth 1 \( -path './usr/local/bigbrother*' -o -path './etc/systemd/system*' \))
	cd .. ; \
	gzip -n data.tar ; \
	ar r pkg.deb debian-binary control.tar.* data.tar.gz ; \
	cp pkg.deb "$$ORIGDIR/$$DEBFILE" ; \
	rm -rf "$$WORKDIR" ; \
	cd "$$ORIGDIR" ; \
	echo "Done, verifying:" ; \
	if dpkg-deb -c "$$DEBFILE" | grep -qE '\./usr/?$$|\./usr/local/?$$'; then \
	  echo "WARNING: bare dirs still present" ; \
	else \
	  echo "OK: clean" ; \
	fi
rpm:

	tar cf ./bigbrother.tar  ./*.sh ./bigbrotherd ./mirrorwebroot ./bblogger ./bigbrother.conf ./bigbrother_camera.conf ./bigbrother_control.conf ./bigbrotherd.service ./LICENSE ./LICENSE-AIMODEL ./README.txt ./bigbrother_event.conf ./markup_y5onnx.py ./markup_y5onnx.py ./bbeventmonitor_ffy5onnx ./bbcameracontrolshell ./bbptzcameracontrollerd_onvif ./onnx ./octaquad
	gzip ./bigbrother.tar
	mv ./bigbrother.tar.gz ./RPM/SOURCES/org.bigbrothercctv.bigbrother.tar.gz	
	rpmbuild -v -bb --clean ./RPM/SPECS/bigbrother.spec
	echo "RPM is in ./RPM/RPMS/noarch/"

pkgng:
	echo "Making pkgng package for FreeBSD"
	rm -rf ./pkgng-tmp
	mkdir ./pkgng-tmp
	mkdir -p ./pkgng-tmp${INSTALLDIR}
	cp ./*.sh  ./pkgng-tmp$(INSTALLDIR)
	cp ./bigbrotherd  ./pkgng-tmp$(INSTALLDIR)
	cp -R ./mirrorwebroot  ./pkgng-tmp$(INSTALLDIR)
	cp ./bblogger  ./pkgng-tmp$(INSTALLDIR)
	cp ./bigbrother.conf  ./pkgng-tmp$(INSTALLDIR)
	cp ./bigbrother_camera.conf  ./pkgng-tmp$(INSTALLDIR)
	cp ./rc.bigbrotherd ./pkgng-tmp$(INSTALLDIR)
	cp ./LICENSE*  ./pkgng-tmp$(INSTALLDIR)
	cp ./README.txt  ./pkgng-tmp$(INSTALLDIR)
	cp ./bigbrother_event.conf ./pkgng-tmp$(INSTALLDIR)
	cp ./bigbrother_control.conf ./pkgng-tmp$(INSTALLDIR)
	cp ./markup_y5onnx.py ./pkgng-tmp$(INSTALLDIR)
	cp ./bbeventmonitor_ffy5onnx ./pkgng-tmp$(INSTALLDIR)
	cp ./bbcameracontrolshell ./pkgng-tmp$(INSTALLDIR)
	cp ./bbptzcameracontrollerd_onvif ./pkgng-tmp$(INSTALLDIR)
	cp -R ./onnx ./pkgng-tmp$(INSTALLDIR)
	cp -R ./octaquad ./pkgng-tmp$(INSTALLDIR)
	pkg create -M ./+MANIFEST -r ./pkgng-tmp/tmp/bigbrother-stage

clean:
	rm -rf ./RPM/RPMS/*
	rm -rf ./*.deb
	rm -rf ./RPM/SOURCES/*
	rm -rf ./*.txz
	rm -rf ./pkgng-tmp
	rm -rf ./*.pkg

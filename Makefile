CHECKINSTALLOPTIONS = --pkgname=org.bigbrothercctv.bigbrother -A all --pkggroup=Video \
--pkglicense=GPL --nodoc --maintainer='bigbrothercctv.org' \
--requires='ffmpeg,python3,python3-opencv \(\>=4.10\)'
INSTALLDIR = /usr/local/bigbrother

default:
	echo "Run make deb|rpm|pkgng|clean"

installbinary:
	mkdir $(INSTALLDIR)
	cp ./*.sh  $(INSTALLDIR)
	cp ./bigbrotherd  $(INSTALLDIR)
	cp -R ./mirrorwebroot  $(INSTALLDIR)
	cp ./bblogger  $(INSTALLDIR)
	cp ./bigbrother.conf  $(INSTALLDIR)
	cp ./bigbrother_camera.conf  $(INSTALLDIR)
	cp ./bigbrother_event.conf  $(INSTALLDIR)
	cp ./bigbrotherd.service /etc/systemd/system
	cp ./License.txt  $(INSTALLDIR)
	cp ./README.txt  $(INSTALLDIR)
	cp ./markup_y5onnx.py $(INSTALLDIR)
	cp ./bbeventmonitor_y5onnx $(INSTALLDIR)
	cp -R ./onnx $(INSTALLDIR)

#Use checkinstall to make a .deb
deb:
	#Use checkinstall to make a deb package
	checkinstall -D --install=no $(CHECKINSTALLOPTIONS) make installbinary



rpm:

	tar cf ./bigbrother.tar  ./*.sh ./bigbrotherd ./mirrorwebroot ./bblogger ./bigbrother.conf ./bigbrother_camera.conf ./bigbrotherd.service ./License.txt ./README.txt ./bigbrother_event.conf ./markup_y5onnx.py ./markup_y5onnx.py ./bbeventmonitor_y5onnx ./onnx
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
	cp ./rc.bigbrother ./pkgng-tmp$(INSTALLDIR)
	cp ./License.txt  ./pkgng-tmp$(INSTALLDIR)
	cp ./README.txt  ./pkgng-tmp$(INSTALLDIR)
	cp ./bigbrother_event.conf ./pkgng-tmp$(INSTALLDIR)
	cp ./markup_y5onnx.py ./pkgng-tmp$(INSTALLDIR)
	cp ./bbeventmonitor_y5onnx ./pkgng-tmp$(INSTALLDIR)
	cp -R ./onnx ./pkgng-tmp$(INSTALLDIR)
	pkg create -M ./+MANIFEST -r ./pkgng-tmp

clean:
	rm -rf ./RPM/RPMS/*
	rm -rf ./*.deb
	rm -rf ./RPM/SOURCES/*
	rm -rf ./*.txz
	rm -rf ./pkgng-tmp
	rm -rf ./*.pkg

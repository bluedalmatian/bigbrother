CHECKINSTALLOPTIONS = --pkgname=org.bigbrothercctv.bigbrother -A all --pkggroup=Video \
--pkglicense=GPL --nodoc --maintainer='bigbrothercctv.org' \
--requires=ffmpeg,python3
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
	cp ./bigbrotherd.service /etc/systemd/system
	cp ./License.txt  $(INSTALLDIR)
	cp ./README.txt  $(INSTALLDIR)

#Use checkinstall to make a .deb
deb:
	#Use checkinstall to make a deb package
	checkinstall -D --install=no $(CHECKINSTALLOPTIONS) make installbinary



rpm:

	tar cf ./bigbrother.tar  ./*.sh ./bigbrotherd ./mirrorwebroot ./bblogger ./bigbrother.conf ./bigbrother_camera.conf ./bigbrotherd.service ./License.txt ./README.txt
	gzip ./bigbrother.tar
	mv ./bigbrother.tar.gz ./RPM/SOURCES/org.bigbrothercctv.bigbrother.tar.gz	
	rpmbuild -v -bb --clean ./RPM/SPECS/bigbrother.spec
	echo "RPM is in ./RPM/RPMS/noarch/"

pkgng:
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
	pkg create -M ./+MANIFEST -r ./pkgng-tmp

clean:
	rm -rf ./RPM/RPMS/*
	rm -rf ./*.deb
	rm -rf ./RPM/SOURCES/*
	rm -rf ./*.txz
	rm -rf ./pkgng-tmp
	rm -rf ./*.pkg

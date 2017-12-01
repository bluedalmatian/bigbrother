CHECKINSTALLOPTIONS = --pkgname=org.simple.bigbrother -A all --pkggroup=Video \
--pkglicense=GPL --nodoc --maintainer='simple.org/bigbrother' \
--requires=ffmpeg,python
INSTALLDIR = /usr/local/bigbrother




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
	mv ./bigbrother.tar.gz ./RPM/SOURCES/org.simple.bigbrother.tar.gz	
	rpmbuild -v -bb --clean ./RPM/SPECS/bigbrother.spec
	echo "RPM is in ./RPM/RPMS/noarch/"

clean:
	rm -rf ./RPM/RPMS/*
	rm -rf ./*.deb
	rm -rf ./RPM/SOURCES/*

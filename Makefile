CHECKINSTALLOPTIONS = --pkgname=org.simple.bigbrother -A all --pkggroup=Video \
--pkglicense=GPL --nodoc --maintainer='simple.org/bigbrother' \
--requires=ffmpeg,python
INSTALLDIR = /usr/local/bigbrother


linux: org.simple.bigbrother.deb org.simple.bigbrother.rpm


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



#Use checkinstall to make a .rpm
rpm:
        #Use checkinstall to make a deb package
	checkinstall -R --install=no $(CHECKINSTALLOPTIONS) make installbinary




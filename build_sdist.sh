#!/bin/bash

VERSION='4.4.1'

rm -f pulse2-*.tar.gz pulse2-*.tar.gz.md5
git clean -fdx && ./autogen.sh && ./configure --sysconfdir=/etc --localstatedir=/var --disable-python-check --disable-conf && make distcheck
tar xzvf pulse2-$VERSION.tar.gz
cp setup.py pulse2-$VERSION
cp -frv debian pulse2-$VERSION
tar czvf pulse2-$VERSION.tar.gz pulse2-$VERSION
mv pulse2-$VERSION.tar.gz pulse2_$VERSION.orig.tar.gz
rm -fr pulse2-$VERSION/
if [ $? -eq 0 ]; then
    for tarball in pulse2*.tar.gz; do
        md5sum $tarball > $tarball.md5
    done
fi

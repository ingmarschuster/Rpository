#!/bin/bash

gnutar() {
    if hash gtar 2>/dev/null; then
        gtar "$@"
    else
        tar "$@"
    fi
}


get_tag_content() {
    cat $2 |
    tr '\n' ' ' | 
    sed -e "s|.*\<${1}\>[ \t]*||g" -e  "s|[ \t]*\<\/${1}\>.*||g" 
}

versionfile=`dirname $0`/src/version.xml
srcdir=`dirname $0`/src

plugin_name=`get_tag_content application "$versionfile"`
plugin_release=`get_tag_content release "$versionfile"`


if [ ! -d `dirname $0`/releases/ ]; then
    mkdir -p `dirname $0`/releases/ > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo "Could not create 'releases/' directory" 1>&2
        exit 1
    fi
fi

gnutar --xform="s|^$srcdir|$plugin_name|g" --xform="s|\.template$||g" -czf `dirname $0`/releases/"$plugin_name-$plugin_release.tar.gz" "$srcdir"

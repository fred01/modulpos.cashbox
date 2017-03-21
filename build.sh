#!/bin/bash
tmpdir="$(mktemp -d)"
builddir="$tmpdir/.last_version"
mkdir $builddir
echo "Build to dir $builddir"
# copy without .git and other hidden files
cp -R * "$builddir"

iconv -f UTF8 -t CP1251 "$builddir/lang/ru/options.php" > "$builddir/lang/ru/options.php.cp1251"
iconv -f UTF8 -t CP1251 "$builddir/lang/ru/install/index.php" > "$builddir/lang/ru/install/index.php.cp1251"
iconv -f UTF8 -t CP1251 "$builddir/lang/ru/lib/cashboxmodul.php" > "$builddir/lang/ru/lib/cashboxmodul.php.cp1251"

mv -f "$builddir/lang/ru/options.php.cp1251" "$builddir/lang/ru/options.php"
mv -f "$builddir/lang/ru/install/index.php.cp1251" "$builddir/lang/ru/install/index.php"
mv -f "$builddir/lang/ru/lib/cashboxmodul.php.cp1251" "$builddir/lang/ru/lib/cashboxmodul.php"

pushd "$tmpdir" # .last_version should be in archive
rm ~/tmp/last_version.zip
zip -r ~/tmp/last_version.zip .last_version
popd

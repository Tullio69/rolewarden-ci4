#!/usr/bin/env bash
# S3 driver (Collaudatore ad Hoc): passo 3 della roadmap, versione 1.0.0 definitiva, commit 26784b6 and build/*-1.0.0.zip.
# One suite at a time. Needs RW_DB_USERNAME / RW_DB_PASSWORD (process environment only) and RW_S3_SCRATCH (scratchpad).
# Docker: images s3-col-php:8.3/8.5 from verify-s3.docker/Dockerfile and network s3-net (172.30.5.0/24) are created
# here and removed at the end; containers s3-* are created and removed by verify-s3.run.py.
cd "$(dirname "$0")" || exit 1
: "${RW_DB_USERNAME:?}" "${RW_DB_PASSWORD:?}" "${RW_S3_SCRATCH:?}"
export RW_V8_NEW=26784b6 RW_V7_NEW=26784b6 RW_S3_WORK="$RW_S3_SCRATCH/s3work"
git -C ../.. diff --stat 09e9df8 26784b6 -- . ':!tests/Integration' > verify-s3.diff-09e9df8-26784b6.txt
python verify-s3.package.py 26784b6 > verify-s3.package.26784b6.output.txt 2>&1
python verify-s3.v7package.py > verify-s3.v7package.26784b6.output.txt 2>&1
RW_S2_V8SCRIPT=verify-s2.v8adapted.php RW_V8B_WORK="$RW_S3_SCRATCH/s3v8zip" python verify-s3.suite.py v8zip 26784b6.final.adapted P,E > verify-s3.v8zip.26784b6.console.txt 2>&1
RW_V8B_V7SCRIPT=verify-v8b.v7adapted.php RW_S3_V7WORK="$RW_S3_SCRATCH/s3v7" python verify-s3.suite7.py 26784b6.final.adapted > verify-s3.v7.26784b6.console.txt 2>&1
docker build -q --build-arg PHPV=8.3 -t s3-col-php:8.3 verify-s3.docker
docker build -q --build-arg PHPV=8.5 -t s3-col-php:8.5 verify-s3.docker
docker network create --subnet 172.30.5.0/24 --gateway 172.30.5.1 s3-net
for s in a83 a85 m474p83 ip83 x2; do python verify-s3.run.py "$s" "$s.26784b6"; done
python verify-s3.run.py x2append x2append.sensitivity
docker rmi s3-col-php:8.3 s3-col-php:8.5; docker network rm s3-net
rm -rf "$RW_S3_WORK"

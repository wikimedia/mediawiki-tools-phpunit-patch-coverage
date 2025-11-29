#!/bin/sh

echo "Received args: $@"

while [ $# -gt 0 ]; do
	case "$1" in
	--coverage-clover)
		echo '<?xml version="1.0" encoding="UTF-8"?><coverage><project/></coverage>' > "$2"
		shift
		shift
		;;
	*)
		shift
		;;
	esac
done

#!/bin/bash

EXPECTED_ARGS=1
E_BADARGS=65

if [ $# -ne $EXPECTED_ARGS ]
then
  echo "Usage: `basename $0` {Installpath of Hub Server}"
  exit $E_BADARGS
fi

rm -rf $1

./install_hub_server.sh $1


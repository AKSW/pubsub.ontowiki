#!/bin/bash

EXPECTED_ARGS=1
E_BADARGS=65

if [ $# -ne $EXPECTED_ARGS ]
then
  echo "Usage: `basename $0` {Installpath for Hub Server}"
  exit $E_BADARGS
fi


wget -P $1 http://googleappengine.googlecode.com/files/google_appengine_1.7.4.zip

unzip -d $1 $1/google_appengine_1.7.4.zip

svn checkout http://pubsubhubbub.googlecode.com/svn/trunk/ $1/pubsubhubbub

git clone git://github.com/marianoguerra/tubes.git $1/tubes

# fix a little bug in this file
sed -i 's/from compiler.consts/#from compiler.consts/g' $1/tubes/werkzeug/templates.py

echo "#!/bin/bash
python google_appengine/dev_appserver.py pubsubhubbub/hub" > $1/start_hub_server.sh
chmod +x $1/start_hub_server.sh

echo "#!/bin/bash
cd tubes/ihasfriendz
python main.py" > $1/start_hub_server_example.sh
chmod +x $1/start_hub_server_example.sh

rm -f $1/google_appengine_1.7.4.zip

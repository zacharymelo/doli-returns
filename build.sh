#!/bin/bash
set -e
VERSION=$(grep "\$this->version" module/core/modules/modCustomerreturn.class.php | sed "s/.*= '//;s/'.*//" )
echo "Building customerreturn-${VERSION}.zip …"
rm -rf /tmp/customerreturn-zip
mkdir -p /tmp/customerreturn-zip/customerreturn
cp -r module/* /tmp/customerreturn-zip/customerreturn/
cd /tmp/customerreturn-zip
zip -r "/Users/zacharymelo/doli-returns/customerreturn-${VERSION}.zip" customerreturn/
rm -rf /tmp/customerreturn-zip
echo "Built customerreturn-${VERSION}.zip"

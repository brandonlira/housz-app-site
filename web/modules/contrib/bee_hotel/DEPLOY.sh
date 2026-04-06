# 1 fai pull


PARTENZA="/mnt/d8_composer_sdd/beehotel.pro/web/modules/custom/bee_hotel"
#DIR_DI_PARTENZA_2="/mnt/d8_composer_sdd/d9_sites/d9.bbsavoia.it/themes"
DESTINAZIONE="/mnt/Public32_apr20/beehotel.pro/web/modules/custom/"

echo "";
echo "***********************";
echo "";

echo "PARTENZA_1 : $PARTENZA";
echo "DESTINAZIONE : $DESTINAZIONE";

echo "";
echo " Deploy di  bee_hotel per beehotel.pro ***********************";
echo "";


echo "1. prendiamo il codice aggioranto";
git pull

echo "------";
echo "------";


echo ". vai nella  destinazione";

#copia dal repos git al filesystem di produzione
cd $DESTINAZIONE
echo "------";
echo "------";


cp -Rfvp   $PARTENZA    .


printf '%s %s\n' "$(date)" "$line";

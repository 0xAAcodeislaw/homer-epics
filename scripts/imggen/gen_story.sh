#!/bin/zsh
cd /Users/coben/.zcode/workspace/default/homer-epic/illustrations/story
LEAD="Epic wide action scene:"
STYLE="Vintage 1970s Chinese lianhuanhua picture book art, bold black ink outlines, flat gouache poster coloring, yellowed old paper, full-body figures, dramatic heroic composition, no text"

declare -a NAMES=(iliad_01_cover iliad_02_golden_apple iliad_03_achilles_rage iliad_05_patroclus_death iliad_06_priam_ransom iliad_07_trojan_horse odyssey_01_cover odyssey_02_cyclops odyssey_03_circe odyssey_04_sirens odyssey_05_scylla_charybdis odyssey_06_calypso odyssey_07_bow_contest)
declare -a SCENES=(
"the vast Greek army in bronze armor with countless spears and beached black ships facing the tall stone walls of Troy across a dusty coastal plain, chariots and banners, dramatic clouds"
"young Trojan prince Paris kneeling to hand a glowing golden apple to the smiling goddess Aphrodite, while queen Hera and armored goddess Athena turn away in anger, marble palace hall with columns, flowing robes"
"greatest Greek hero Achilles angrily confronting king Agamemnon before the assembled Greek army, drawing his sword from the scabbard, elders holding his arms, royal tent with bronze tripods"
"Trojan prince Hector in bright bronze armor striking down young Patroclus with a spear in front of the high walls of Troy, fallen warriors around, chariots in the dust"
"old white-bearded Trojan king Priam kneeling before seated hero Achilles and kissing his hands, the body of Hector wrapped in white cloth on a cart behind them, torch-lit tent, both men weeping"
"an enormous wooden horse on wheels being pulled through the great stone gate of Troy by celebrating Trojans with garlands and drums, at dusk"
"a lone ancient Greek sailing ship riding huge rolling blue-green waves under a stormy sky, bearded hero gripping the rudder at the stern, white foam splashing over the deck"
"a giant one-eyed cyclops shepherd lifting a huge boulder at his cave exit, cunning Greek king Odysseus handing him a wineskin, frightened sailors huddling behind a flock of sheep in the smoky cave, firelight"
"beautiful sorceress Circe in a flowing gown stirring a golden potion in a great bronze cauldron inside a marble hall in the woods, enchanted sailors with pig heads grazing around, wary Odysseus drawing his sword"
"Greek king Odysseus tightly bound to the wooden mast of his sailing ship, straining toward three winged sirens singing on jagged rocks, bearded sailors rowing hard, rolling blue-green waves"
"a six-headed sea monster snatching screaming sailors from the deck of a Greek ship while a giant whirlpool churns beside the narrow rocky strait, Odysseus shouting at the tiller"
"lonely hero Odysseus sitting weeping on a rocky shore of a lush island, gazing across the sea toward home, the goddess Calypso in flowing robes watching from a cave entrance above, calm turquoise water"
"weathered Odysseus in ragged clothes stringing his great bow and aiming through a row of bronze axe heads while rich suitors recoil in fear in a torch-lit great stone hall, queen Penelope watching from a balcony"
)

i=0
for idx in $(seq 1 ${#NAMES[@]}); do
  name="${NAMES[$((idx-1))]}"
  scene="${SCENES[$((idx-1))]}"
  if [ -s "$name.jpg" ] && file -b "$name.jpg" | head -c 4 | grep -q "JPEG\|PNG"; then echo "SKIP $name"; continue; fi
  P="$LEAD $scene. $STYLE"
  ENC=$(python3 -c "import urllib.parse,sys;print(urllib.parse.quote(sys.argv[1]))" "$P")
  ok=0
  for try in $(seq 1 14); do
    curl -sL --max-time 150 -o "$name.jpg" "https://image.pollinations.ai/prompt/${ENC}?width=1024&height=768&nologo=true&model=sana&seed=$((idx*37+5))"
    T=$(file -b "$name.jpg" | head -c 10)
    case "$T" in
      JPEG*|PNG*) ok=1; echo "$(date +%H:%M:%S) OK  $name (try $try)" >> progress.log; echo "OK  $name (try $try)"; break;;
      *) sleep 48;;
    esac
  done
  [ $ok -eq 0 ] && echo "$(date +%H:%M:%S) FAIL $name" >> progress.log && echo "FAIL $name"
done
echo "ALL DONE" >> progress.log

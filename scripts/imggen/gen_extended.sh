#!/bin/zsh
OUT=/Users/coben/.zcode/workspace/default/homer-epic/illustrations/extended
mkdir -p "$OUT"; cd "$OUT"
STYLE="Vintage 1970s Chinese lianhuanhua picture book art, bold black ink outlines, flat gouache poster coloring, yellowed old paper, dramatic heroic composition, no text"
declare -a NAMES=(
ext_iliad_02_review ext_iliad_03_duel_helen ext_iliad_05_diomedes ext_iliad_06_farewell
ext_iliad_07_ajax_duel ext_iliad_09_embassy ext_iliad_18_forge ext_iliad_23_games
ext_odyssey_02_night_sail ext_odyssey_04_sparta_feast ext_odyssey_06_nausicaa ext_odyssey_08_bard
ext_odyssey_11_underworld ext_odyssey_13_homecoming ext_odyssey_14_swineherd ext_odyssey_17_argos
ext_odyssey_23_reunion
comic_01_horse_built comic_02_gate comic_03_feast comic_04_night)
declare -a SCENES=(
"Army review scene: Odysseus striding with a long scepter among thousands of seated bronze warriors and beached ships on a wide sandy shore, beating a troublemaker, banners flying"
"Duel scene: two ancient Greek warriors fighting with spears before the walls of Troy, a beautiful veiled woman watching from the city battlements above with old kings beside her"
"Battle scene: a young Greek warrior in bronze armor thrusting his spear upward at a glowing goddess who recoils in a beam of light, battlefield chaos behind"
"Farewell scene: a helmeted warrior taking off his plumed helmet and reaching toward a baby son held up by his veiled wife at a great city gate, tender moment"
"Duel at dusk: two giant warriors dueling with spears and shields before the walls of Troy, two heralds holding staffs separating them, torchlight"
"Embassy scene: three elder Greek nobles carrying gifts entering a tent where a young hero plays a lyre, golden goblets and treasure chests in the foreground"
"Forge scene: a muscular limping smith god hammering a huge glowing shield on an anvil amid sparks and flame, golden robot maidens assisting, dark workshop"
"Games scene: a four-horse chariot race rounding a stone post at full gallop, dust flying, crowds of spectators cheering on a hillside"
"Night sail scene: a young man stepping aboard a small sailing ship at night with burning torches, sailors hauling ropes, stars above"
"Palace feast scene: a rich bronze-walled palace hall with a regal king and a beautiful queen hosting young guests, wedding decorations, hounds under tables"
"River scene: a graceful princess with her handmaids doing laundry and playing ball by a riverbank, a rugged bearded stranger kneeling before her wrapped in a cloak"
"Bard scene: a blind old bard singing while playing a lyre at a torch-lit feast, a rugged guest in the crowd weeping into his cloak"
"Underworld scene: a Greek hero pouring a libation into a dark pit while pale ghostly figures drift toward him out of the darkness, moths and mist"
"Homecoming scene: sailors carrying a sleeping man on a cot off a ship onto a rocky island shore at dawn, treasure chests beside him, mist over the sea"
"Hut scene: an old swineherd warmly offering bread and wine to a ragged beggar inside a rustic mountain hut, boars and pig-pens outside, firelight"
"Street scene: an old feeble dog lying on a dung heap lifting its head to recognize a ragged beggar passing by, town street with stone houses"
"Reunion scene: a weathered man and a graceful woman embracing beside a carved wooden bedposts in a torch-lit bedchamber, tears and joy, bronze lamps"
"Workshop scene: Greek craftsmen building a giant wooden horse with planks and hammers inside a fortified camp, Odysseus directing, ships in background"
"Gate scene: joyful Trojan people with flower garlands pulling a giant wooden horse on rolling logs through a great city gate, priests protesting in vain"
"Feast scene: Trojans celebrating wildly at night with wine cups and dancers around the giant wooden horse standing in the town square, torches and bunting"
"Night scene: Greek warriors slipping out of a hidden door in the belly of the giant wooden horse under moonlight, torches of celebration in the distance"
)
for idx in $(seq 1 ${#NAMES[@]}); do
  name="${NAMES[$((idx-1))]}"; scene="${SCENES[$((idx-1))]}"
  [ -s "$name.jpg" ] && file -b "$name.jpg" | head -c 4 | grep -q "JPEG\|PNG" && { echo "SKIP $name"; continue; }
  P="$scene. $STYLE"
  ENC=$(python3 -c "import urllib.parse,sys;print(urllib.parse.quote(sys.argv[1]))" "$P")
  ok=0
  for try in $(seq 1 14); do
    curl -sL --max-time 150 -o "$name.jpg" "https://image.pollinations.ai/prompt/${ENC}?width=1024&height=768&nologo=true&model=sana&seed=$((idx*41+7)))"
    T=$(file -b "$name.jpg" | head -c 10)
    case "$T" in JPEG*|PNG*) ok=1; echo "$(date +%H:%M:%S) OK  $name (try $try)" >> gen.log; break;; *) sleep 48;; esac
  done
  [ $ok -eq 0 ] && echo "FAIL $name" >> gen.log
done
echo "EXTENDED DONE" >> gen.log

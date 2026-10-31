#!/bin/zsh
mkdir -p /Users/coben/.zcode/workspace/default/homer-epic/illustrations/story/regen
cd /Users/coben/.zcode/workspace/default/homer-epic/illustrations/story/regen
STYLE="Vintage 1970s Chinese lianhuanhua picture book art, bold black ink outlines, flat gouache poster coloring, yellowed old paper, full-body figures, dramatic heroic composition, no text"

declare -a NAMES=(iliad_01_cover iliad_02_golden_apple iliad_03_achilles_rage iliad_05_patroclus_death iliad_07_trojan_horse odyssey_01_cover odyssey_02_cyclops odyssey_03_circe odyssey_04_sirens odyssey_05_scylla_charybdis)
declare -a SCENES=(
"Wide panoramic scene: hundreds of Greek bronze warriors with spears marching across a dusty shore toward the distant walled city of Troy, long black ships pulled up on the beach, dramatic clouds overhead"
"Ceremony scene: young beardless prince Paris on one knee presenting a glowing golden apple to the graceful goddess Aphrodite, while queen Hera and armored goddess Athena turn away in anger, golden marble palace columns"
"Tent interior scene: king Agamemnon raising a golden scepter in anger at the young hero Achilles who grips his sword hilt, two elders holding Achilles' arms back, bronze weapons and cups around a camp table"
"Battle scene: prince Hector in bright bronze armor thrusting his spear into the young warrior Patroclus who falls backward beside the high stone walls of Troy, scattered shields and fallen warriors on the ground"
"Night celebration scene: a giant wooden horse statue standing on wooden wheels at the open gate of Troy, joyful Trojan people with flower garlands pulling it with long ropes, torches glowing at dusk"
"Sea adventure scene: a lone ancient Greek sailing ship riding huge rolling waves, a bearded hero in armor standing at the stern gripping the rudder, dramatic stormy sky with white foam"
"Cave interior scene: a huge one-eyed giant sitting beside a blazing campfire holding a wine cup, several frightened sailors hiding behind a flock of sheep along the cave wall, smoky firelight"
"Hall scene: the sorceress Circe in a purple gown stirring a golden potion in a big bronze cauldron, several sailors with pig heads and pig bodies grazing on the floor around her, wary Odysseus with drawn sword at the door"
"Sea scene: hero Odysseus tightly bound with ropes to the wooden mast of his sailing ship, three winged sirens with bird bodies singing from jagged black rocks ahead, sailors rowing hard with plugged ears, rolling green waves"
"Strait scene: a Greek ship rowing fast through a narrow rocky strait, a giant whirlpool spinning at the cliff base on one side, a six-headed sea monster rearing up from the cliff top on the other side, terrified sailors"
)

for idx in $(seq 1 ${#NAMES[@]}); do
  name="${NAMES[$((idx-1))]}"
  scene="${SCENES[$((idx-1))]}"
  if [ -s "$name.jpg" ] && file -b "$name.jpg" | head -c 4 | grep -q "JPEG\|PNG"; then echo "SKIP $name"; continue; fi
  P="$scene. $STYLE"
  ENC=$(python3 -c "import urllib.parse,sys;print(urllib.parse.quote(sys.argv[1]))" "$P")
  ok=0
  for try in $(seq 1 14); do
    curl -sL --max-time 150 -o "$name.jpg" "https://image.pollinations.ai/prompt/${ENC}?width=1024&height=768&nologo=true&model=sana&seed=$((idx*53+11)))"
    T=$(file -b "$name.jpg" | head -c 10)
    case "$T" in
      JPEG*|PNG*) ok=1; echo "$(date +%H:%M:%S) OK  $name (try $try)" >> regen.log; break;;
      *) sleep 48;;
    esac
  done
  [ $ok -eq 0 ] && echo "FAIL $name" >> regen.log
done
echo "REGEN DONE" >> regen.log

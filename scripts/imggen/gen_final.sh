#!/bin/zsh
mkdir -p /Users/coben/.zcode/workspace/default/homer-epic/illustrations/story/final
cd /Users/coben/.zcode/workspace/default/homer-epic/illustrations/story/final
STYLE="Vintage 1970s Chinese lianhuanhua picture book art, bold black ink outlines, flat gouache poster coloring, yellowed old paper, dramatic heroic composition, no text"

declare -a NAMES=(iliad_01_cover iliad_07_trojan_horse odyssey_02_cyclops odyssey_04_sirens odyssey_05_scylla_charybdis odyssey_06_calypso odyssey_07_bow_contest)
declare -a SCENES=(
"Formation scene: many ancient Greek bronze warriors standing in rows with long spears and round shields on a sandy beach, wooden sailing ships beached behind them, a walled city on a hill in the far distance, dramatic sky"
"A huge wooden horse statue standing on four wooden wheels in a dusty square before a tall stone city gate, cheerful people with flower garlands dancing around it, sunset light"
"A giant bald man with one large round eye in the middle of his forehead, sitting on a stone bench beside a campfire inside a dark cave, holding a wooden wine cup, a flock of sheep huddled in the corner"
"A bearded man tied with thick ropes to the tall wooden mast of a sailing ship on the open sea, three winged women with white bird wings singing on black jagged rocks ahead, green rolling waves"
"A wooden sailing ship rowing between two towering cliffs on the sea, a giant swirling whirlpool in the water below the left cliff, six serpent heads rising over the top of the right cliff, spray and storm clouds"
"A bearded man in ragged clothes sitting on a rock by a turquoise shore, weeping with his head in his hands, a lush green island with a cave behind him, gentle waves"
"A bearded man in a brown cloak drawing a large bow and aiming an arrow through a row of twelve bronze axe heads set in a line, nobles in togas watching anxiously in a torch-lit palace hall"
)

for idx in $(seq 1 ${#NAMES[@]}); do
  name="${NAMES[$((idx-1))]}"
  scene="${SCENES[$((idx-1))]}"
  if [ -s "$name.jpg" ] && file -b "$name.jpg" | head -c 4 | grep -q "JPEG\|PNG"; then echo "SKIP $name"; continue; fi
  P="$scene. $STYLE"
  ENC=$(python3 -c "import urllib.parse,sys;print(urllib.parse.quote(sys.argv[1]))" "$P")
  ok=0
  for try in $(seq 1 14); do
    curl -sL --max-time 150 -o "$name.jpg" "https://image.pollinations.ai/prompt/${ENC}?width=1024&height=768&nologo=true&model=sana&seed=$((idx*29+3)))"
    T=$(file -b "$name.jpg" | head -c 10)
    case "$T" in
      JPEG*|PNG*) ok=1; echo "$(date +%H:%M:%S) OK  $name (try $try)" >> final.log; break;;
      *) sleep 48;;
    esac
  done
  [ $ok -eq 0 ] && echo "FAIL $name" >> final.log
done
echo "FINAL DONE" >> final.log

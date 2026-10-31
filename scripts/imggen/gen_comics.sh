#!/bin/zsh
cd /Users/coben/.zcode/workspace/default/homer-epic/illustrations/extended
STYLE="Vintage 1970s Chinese lianhuanhua picture book art, bold black ink outlines, flat gouache poster coloring, yellowed old paper, dramatic composition, no text"
declare -a NAMES=(comic_01_horse_built comic_02_gate comic_03_feast comic_04_night)
declare -a SCENES=(
"Greek craftsmen building a giant wooden horse with planks and hammers inside a fortified camp, Odysseus directing, ships in background"
"joyful Trojan people with flower garlands pulling a giant wooden horse on rolling logs through a great stone city gate, priests protesting in vain"
"Trojans celebrating wildly at night with wine cups and dancers around a giant wooden horse standing in the town square, torches and bunting"
"Greek warriors slipping out of a hidden door in the belly of a giant wooden horse under moonlight, celebration torches far away"
)
for idx in $(seq 1 4); do
  name="${NAMES[$((idx-1))]}"; scene="${SCENES[$((idx-1))]}"
  [ -s "$name.jpg" ] && file -b "$name.jpg" | head -c 4 | grep -q "JPEG\|PNG" && continue
  P="$scene. $STYLE"
  ENC=$(python3 -c "import urllib.parse,sys;print(urllib.parse.quote(sys.argv[1]))" "$P")
  for try in $(seq 1 20); do
    curl -sL --max-time 150 -o "$name.jpg" "https://image.pollinations.ai/prompt/${ENC}?width=1024&height=768&nologo=true&model=sana&seed=$((idx*77+13)))"
    T=$(file -b "$name.jpg" | head -c 10)
    case "$T" in JPEG*|PNG*) echo "$(date +%H:%M:%S) OK  $name (try $try)" >> gen.log; break;; *) sleep 46;; esac
  done
done
echo "COMICS DONE" >> gen.log

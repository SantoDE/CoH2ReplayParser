<?php

include_once 'CoH2Replay.php';

class CoH2ReplayParser {

	private $replay;	// CoH2Replay object
	private $stream;	// CoH2Stream object (from $replay)
	
	public function __construct($file) {
		$this->replay = new CoH2Replay($file);
		$this->stream = $this->replay->getStream();
	}

	public function parse() {
		
		$this->replay->setVersion($this->stream->readUInt32());
		
		$this->replay->setGametype($this->stream->readText(8));
		
		$time = "";
		while ($this->stream->readUInt16() != 0) {
			$this->stream->skip(-2);
			$time = $time . $this->stream->readText(2);
		}
		$this->replay->setDateTime($time);
		
		$this->stream->seek(76);
		
		$this->parseChunky();
		$this->parseChunky();
		
		$this->stream->close();
		return $this->replay;
	}
	
	private function parseChunky() {
		
		if (!($this->stream->readText(12) === "Relic Chunky")) return false;
		
		$this->stream->skip(4);
		
		if ($this->stream->readUInt32() != 3) return false;
		
		$this->stream->skip(4);
		
		$this->stream->skip($this->stream->readUInt32() - 28);
		
		while ($this->parseChunk());
		
		return true;
	}
	
	private function parseChunk() {
		
		$chunkType = $this->stream->readText(8);
		if (!(substr($chunkType, 0, 4) === "FOLD" || substr($chunkType, 0, 4) === "DATA")) {
			$this->stream->skip(-8);
			return false;
		}
		
		$chunkVersion = $this->stream->readUInt32();
		$chunkLength = $this->stream->readUInt32();
		$chunkNameLength = $this->stream->readUInt32();
		
		$this->stream->skip(8);
		
		$chunkName = null;
		if ($chunkNameLength > 0)
			$chunkName = $this->stream->readText($chunkNameLength);
		
		$startPosition = $this->stream->getPosition();
		
		if (substr($chunkType, 0, 4) === "FOLD") {
			while ($this->stream->getPosition() < $startPosition + $chunkLength)
				$this->parseChunk();
		}
		
		if ($chunkType === "DATASDSC" && $chunkVersion == 0x7dd) {
			$this->stream->skip(16);
			
			$this->stream->skip(12 + 2 * $this->stream->readUInt32());
			
			$this->replay->setModName($this->stream->readText($this->stream->readUInt32()));
			
			$this->replay->setMapFile($this->stream->readText($this->stream->readUInt32()));
			
			$this->stream->skip(16);
			
			$this->replay->setMapName($this->stream->readText(2 * $this->stream->readUInt32()));
			
			$this->stream->skip(4);
			
			$this->replay->setMapDescription($this->stream->readText(2 * $this->stream->readUInt32()));
			
			$this->stream->skip(4);
			
			$this->replay->setMapWidth($this->stream->readUInt32());
			
			$this->replay->setMapHeight($this->stream->readUInt32());
			
			$this->stream->skip(47);
			
			if ($this->stream->readUInt32() > 0) {
				$this->stream->skip(-4);
				$this->replay->setSeason($this->stream->readText($this->stream->readUInt32()));
			}
		}
		
		if ($chunkType === "DATADATA" && $chunkVersion == 0x4) {
			$this->stream->skip(29);
			
			$numPlayers = $this->stream->readUInt32();
			
			for ($i = 0; $i < $numPlayers; $i ++)
				$this->replay->addPlayer($this->parsePlayer());
		}
		
		if ($chunkType === "DATABASE" && $chunkVersion == 0xff) {
			// chunk type not yet included in CoH2 replay file
		}
		
		$this->stream->seek($startPosition + $chunkLength);
		
		return true;
	}
	
	private function parsePlayer() {
	
		$this->stream->skip(1);
		
		$player = CoH2Player::createWithName($this->stream->readText(2 * $this->stream->readUInt32()));
		
		$player->setTeam($this->stream->readUInt32());
		
		$player->setFaction($this->stream->readUInt32());
		
		$this->stream->skip(85);
		
		$numBulletins = $this->stream->readUInt32();
		
		for ($i = 0; $i < $numBulletins; $i ++) {
			$player->addBulletin($this->stream->readText($this->stream->readUInt32()));
			$this->stream->skip(4);
		}
		
		return $player;
	}
}

// info display

$parser = new CoH2ReplayParser("ggw.Coon.rec");
$replay = $parser->parse();

$version = $replay->getVersion();
$gametype = $replay->getGametype();
$datetime = $replay->getDateTime();
$modname = $replay->getModName();
$mapFile = $replay->getMapFile();
$mapName = $replay->getMapName();
$mapDescription = $replay->getMapDescription();
$mapWidth = $replay->getMapWidth();
$mapHeight = $replay->getMapHeight();
$season = $replay->getSeason();

echo "Version: $version<br />";
echo "Gametype: $gametype</br />";
echo "Mod: $modname<br />";
echo "Map File: $mapFile<br />";
echo "Map Name: $mapName<br />";
echo "Map Description: $mapDescription<br />";
echo "Map Width: $mapWidth<br />";
echo "Map Height: $mapHeight<br />";
echo "Season: $season<br />";

echo "<br />";

$players = $replay->getPlayers();

for ($i = 0; $i < count($players); $i ++) {
	echo "Player $i:<br />";
	
	$name = $players[$i]->getName();
	echo "Name: $name<br />";
	
	$team = $players[$i]->getTeam();
	echo "Team: $team<br />";
	
	$faction = $players[$i]->getFaction();
	echo "Faction: $faction<br />";
	
	$bulletins = $players[$i]->getBulletins();
	
	for ($j = 0; $j < count($bulletins); $j ++) {
		echo "Bulletin $j: $bulletins[$j]<br />";
	}
	echo "<br />";
}

?>
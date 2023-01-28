<?php

function PlayAura($cardID, $player, $number = 1, $isToken = false)
{
  global $CS_NumAuras;
  $otherPlayer = ($player == 1 ? 2 : 1);
  if (CardType($cardID) == "T") $isToken = true;
  if (DelimStringContains(CardSubType($cardID), "Affliction")) {
    $otherPlayer = $player;
    $player = ($player == 1 ? 2 : 1);
  }
  $auras = &GetAuras($player);
  if ($cardID == "ARC112") $number += CountCurrentTurnEffects("ARC081", $player);
  if ($cardID == "MON104") {
    $index = SearchArsenalReadyCard($player, "MON404");
    if ($index > -1) TheLibrarianEffect($player, $index);
  }
  $myHoldState = AuraDefaultHoldTriggerState($cardID);
  if ($myHoldState == 0 && HoldPrioritySetting($player) == 1) $myHoldState = 1;
  $theirHoldState = AuraDefaultHoldTriggerState($cardID);
  if ($theirHoldState == 0 && HoldPrioritySetting($otherPlayer) == 1) $theirHoldState = 1;
  for ($i = 0; $i < $number; ++$i) {
    array_push($auras, $cardID);
    array_push($auras, 2); //Status
    array_push($auras, AuraPlayCounters($cardID)); //Miscellaneous Counters
    array_push($auras, 0); //Attack counters
    array_push($auras, ($isToken ? 1 : 0)); //Is token 0=No, 1=Yes
    array_push($auras, AuraNumUses($cardID));
    array_push($auras, GetUniqueId());
    array_push($auras, $myHoldState); //My Hold priority for triggers setting 2=Always hold, 1=Hold, 0=Don't hold
    array_push($auras, $theirHoldState); //Opponent Hold priority for triggers setting 2=Always hold, 1=Hold, 0=Don't hold
  }
  if (DelimStringContains(CardSubType($cardID), "Affliction")) IncrementClassState($otherPlayer, $CS_NumAuras, $number);
  elseif ($cardID != "ELE111") IncrementClassState($player, $CS_NumAuras, $number);
}

function AuraNumUses($cardID)
{
  switch ($cardID) {
    case "EVR140":
    case "EVR141":
    case "EVR142":
    case "EVR143":
    case "UPR005":
      return 1;
    default:
      return 0;
  }
}

function TokenCopyAura($player, $index)
{
  $auras = &GetAuras($player);
  PlayAura($auras[$index], $player, 1, true);
}

function PlayMyAura($cardID)
{
  global $currentPlayer;
  PlayAura($cardID, $currentPlayer, 1);
}

//Scope = Private
//Call DestroyAura to destroy an aura
function AuraDestroyed($player, $cardID, $isToken = false)
{
  $auras = &GetAuras($player);
  for ($i = 0; $i < count($auras); $i += AuraPieces()) {
    switch ($auras[$i]) {
      case "EVR141":
        if (!$isToken && $auras[$i + 5] > 0 && ClassContains($cardID, "ILLUSIONIST", $player)) {
          --$auras[$i + 5];
          PlayAura("MON104", $player);
        }
        break;
      case "DYN072":
        if ($auras[$i] == $cardID) {
          $char = &GetPlayerCharacter($player);
          for ($j = 0; $j < count($char); $j += CharacterPieces()) {
            if (CardSubType($char[$j]) == "Sword") $char[$j + 3] = 0;
          }
        }
        break;
      default:
        break;
    }
  }
  $goesWhere = GoesWhereAfterResolving($cardID);
  for ($i = 0; $i < SearchCount(SearchAurasForCard("MON012", $player)); ++$i) {
    if (TalentContains($cardID, "LIGHT", $player)) $goesWhere = "SOUL";
    if(CardType($cardID) != "T" && $isToken) WriteLog("<span style='color:red;'>The card is not put in your soul from Merciful Retribution because it is a token copy.</span>");
    DealArcane(1, 0, "STATIC", "MON012", false, $player);
  }

  if (HasWard($cardID) && SearchCharacterActive($player, "DYN213") && !$isToken) {
    $char = &GetPlayerCharacter($player);
    $index = FindCharacterIndex($player, "DYN213");
    $char[$index + 1] = 1;
    GainResources($player, 1);
  }

  if (CardType($cardID) == "T" || $isToken) return; //Don't need to add to anywhere if it's a token
  switch ($goesWhere) {
    case "GY":
      if (DelimStringContains(CardSubType($cardID), "Affliction")) {
        $player = ($player == 1 ? 2 : 1);
      } //Swap the player if it's an affliction
      AddGraveyard($cardID, $player, "PLAY");
      break;
    case "SOUL":
      AddSoul($cardID, $player, "PLAY");
      break;
    case "BANISH":
      BanishCardForPlayer($cardID, $player, "PLAY", "NA");
      break;
    default:
      break;
  }
}

function AuraLeavesPlay($player, $index)
{
  $auras = &GetAuras($player);
  $cardID = $auras[$index];
  $uniqueID = $auras[$index + 6];
  $otherPlayer = ($player == 1 ? 2 : 1);
  switch($cardID)
  {
    case "DYN221": case "DYN222": case "DYN223":
      $theirBanish = &GetBanish($otherPlayer);
      $banishIndex = -1;
      for($i=0; $i<count($theirBanish); $i+=BanishPieces())
      {
        if($theirBanish[$i+1] == "DYN221-" . $uniqueID) $banishIndex = $i;
      }
      if($banishIndex > -1)
      {
        $banishCard = $theirBanish[$banishIndex];
        RemoveBanish($otherPlayer, $banishIndex);
        PlayAura($banishCard, $otherPlayer);
      }
      break;
    default: break;
  }
}

function AuraPlayCounters($cardID)
{
  switch ($cardID) {
    case "CRU075":
      return 1;
    case "EVR107":
      return 3;
    case "EVR108":
      return 2;
    case "EVR109":
      return 1;
    case "UPR140":
      return 3;
    default:
      return 0;
  }
}

function DestroyAuraUniqueID($player, $uniqueID)
{
  $index = SearchAurasForUniqueID($uniqueID, $player);
  if($index != -1) DestroyAura($player, $index, $uniqueID);
}

function DestroyAura($player, $index, $uniqueID="")
{
  $auras = &GetAuras($player);
  $cardID = $auras[$index];
  $isToken = $auras[$index + 4] == 1;
  AuraDestroyed($player, $cardID, $isToken);
  if ($uniqueID != "") $index = SearchAurasForUniqueID($uniqueID, $player);
  AuraLeavesPlay($player, $index);
  if (IsSpecificAuraAttacking($player, $index)) {
    CloseCombatChain();
  }
  for ($j = $index + AuraPieces() - 1; $j >= $index; --$j) {
    unset($auras[$j]);
  }
  $auras = array_values($auras);
  return $cardID;
}

function AuraCostModifier()
{
  global $currentPlayer;
  $otherPlayer = ($currentPlayer == 1 ? 2 : 1);
  $myAuras = &GetAuras($currentPlayer);
  $theirAuras = &GetAuras($otherPlayer);
  $modifier = 0;
  for ($i = count($myAuras) - AuraPieces(); $i >= 0; $i -= AuraPieces()) {
    switch ($myAuras[$i]) {
      case "ELE111":
        $modifier += 1;
        AddLayer("TRIGGER", $currentPlayer, "ELE111", "-", "-", $myAuras[$i + 6]);
        break;
      default:
        break;
    }
  }

  for ($i = count($theirAuras) - AuraPieces(); $i >= 0; $i -= AuraPieces()) {
    switch ($theirAuras[$i]) {
      case "ELE146":
        $modifier += 1;
        break;
      default:
        break;
    }
  }
  return $modifier;
}


// Start of Start Phase with Start of Turn Abilities. No players gain priority // CR 2.1 - 4.2.1. Players do not get priority during the Start Phase.
// Start of Action Phase give players priority // CR 2.1 - 4.3.1. The “beginning of the action phase” event occurs and abilities that trigger at the beginning of the action phase are triggered.
function AuraStartTurnAbilities()
{
  global $mainPlayer, $CS_EffectContext;
  $auras = &GetAuras($mainPlayer);
  for ($i = count($auras) - AuraPieces(); $i >= 0; $i -= AuraPieces()) {
    SetClassState($mainPlayer, $CS_EffectContext, $auras[$i]);
    switch ($auras[$i]) {
      case "WTR046":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "WTR047":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "WTR054": case "WTR055": case "WTR056":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "WTR069": case "WTR070": case "WTR071":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "WTR072": case "WTR073": case "WTR074":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "WTR075":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "ARC162":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "MON186":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "MON006":
        GenesisStartTurnAbility(); // No priority. Start Phase trigger.
        break;
      case "CRU028":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
      case "CRU029": case "CRU030": case "CRU031":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "CRU038": case "CRU039": case "CRU040":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "CRU075":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "CRU144":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "ELE025": case "ELE026": case "ELE027":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "ELE028": case "ELE029": case "ELE030":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "ELE206":
      case "ELE207":
      case "ELE208":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "ELE109":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "EVR107": case "EVR108": case "EVR109":
        WriteLog(CardLink($auras[$i], $auras[$i]) . " trigger creates a layer.");
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "EVR131": case "EVR132": case "EVR133":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "UPR190":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "UPR218": case "UPR219": case "UPR220":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      case "DYN013": case "DYN014": case "DYN015":
        if ($auras[$i] == "DYN013") $amount = 3;
        else if ($auras[$i] == "DYN014") $amount = 2;
        else $amount = 1;
        WriteLog(CardLink($auras[$i], $auras[$i]) . " give +" . $amount . "power to your next 6 or more base power attack.");
        AddCurrentTurnEffect($auras[$i], $mainPlayer, "ARENA");
        DestroyAuraUniqueID($mainPlayer, $auras[$i + 6]);
        break;
      case "DYN029":
        DestroyAuraUniqueID($mainPlayer, $auras[$i + 6]);
        $hand = &GetHand($mainPlayer);
        if(count($hand) == 0)
        {
          Draw($mainPlayer, false);
          WriteLog("Drew a card from Never Yield.");
        }
        if(PlayerHasLessHealth($mainPlayer))
        {
          GainHealth(2, $mainPlayer);
          WriteLog("Gained 2 health from Never Yield.");
        }
        if(PlayerHasFewerEquipment($mainPlayer))
        {
          AddDecisionQueue("MULTIZONEINDICES", $mainPlayer, "MYCHAR:type=E;hasNegCounters=true");
          AddDecisionQueue("SETDQCONTEXT", $mainPlayer, "Choose which equipment to remove a -1 defense counter", 1);
          AddDecisionQueue("CHOOSEMULTIZONE", $mainPlayer, "<-", 1);
          AddDecisionQueue("MZGETCARDINDEX", $mainPlayer, "-", 1);
          AddDecisionQueue("REMOVENEGDEFCOUNTER", $mainPlayer, "-", 1);
          WriteLog("Removed a -1 counter from Never Yield.");
        }
        break;
      case "DYN033": case "DYN034": case "DYN035":
        if ($auras[$i] == "DYN033") $amount = 3;
        else if ($auras[$i] == "DYN034") $amount = 2;
        else $amount = 1;
        WriteLog(CardLink($auras[$i], $auras[$i]) . " give " . $amount . " health to target hero.");
        GainHealth($amount, $mainPlayer);
        DestroyAuraUniqueID($mainPlayer, $auras[$i + 6]);
        break;
      case "DYN048":
        WriteLog(CardLink($auras[$i], $auras[$i]) . " create a " . CardLink("DYN065", "DYN065") . " in your hand.");
        AddPlayerHand("DYN065", $mainPlayer, "-");
        DestroyAuraUniqueID($mainPlayer, $auras[$i + 6]);
        break;
      case "DYN053": case "DYN054": case "DYN055":
        if ($auras[$i] == "DYN053") $amount = 3;
        else if ($auras[$i] == "DYN054") $amount = 2;
        else $amount = 1;
        WriteLog(CardLink($auras[$i], $auras[$i]) ." creates a " . CardLink("DYN065", "DYN065") . " and give it +" . $amount);
        $index = BanishCardForPlayer("DYN065", $mainPlayer, "-", "TT", $mainPlayer);
        $banish = &GetBanish($mainPlayer);
        AddDecisionQueue("PASSPARAMETER", $mainPlayer, $banish[$index+2]);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $mainPlayer, $auras[$i] . "," . "BANISH");
        DestroyAuraUniqueID($mainPlayer, $auras[$i + 6]);
        break;
      case "DYN073": case "DYN074": case "DYN075":
        if ($auras[$i] == "DYN073") $amount = 3;
        else if ($auras[$i] == "DYN074") $amount = 2;
        else $amount = 1;
        WriteLog(CardLink($auras[$i], $auras[$i]) . " give +" . $amount . "power to your weapon next attack.");
        AddCurrentTurnEffect($auras[$i], $mainPlayer, "ARENA");
        DestroyAuraUniqueID($mainPlayer, $auras[$i + 6]);
        break;
      case "DYN098": case "DYN099": case "DYN100":
        if ($auras[$i] == "DYN098") $amount = 3;
        else if ($auras[$i] == "DYN099") $amount = 2;
        else $amount = 1;
        DestroyAuraUniqueID($mainPlayer, $auras[$i + 6]);
        $searchHyper = CombineSearches(SearchDiscardForCard($mainPlayer, "ARC036", "DYN111", "DYN112"), SearchBanishForCardMulti($mainPlayer, "ARC036", "DYN111", "DYN112"));
        $countHyper = count(explode(",", $searchHyper));
        if ($amount > $countHyper) $amount = $countHyper;
        for ($i = 0; $i < $amount; ++$i) {
          AddDecisionQueue("MULTIZONEINDICES", $mainPlayer, "MYDISCARD:cardID=ARC036;cardID=DYN111;cardID=DYN112&MYBANISH:cardID=ARC036;cardID=DYN111;cardID=DYN112");
          AddDecisionQueue("SETDQCONTEXT", $mainPlayer, "Choose an item to put into play");
          AddDecisionQueue("CHOOSEMULTIZONE", $mainPlayer, "<-", 1);
          AddDecisionQueue("SETDQVAR", $mainPlayer, "0", 1);
          AddDecisionQueue("MZGETCARDID", $mainPlayer, "-", 1);
          AddDecisionQueue("PUTPLAY", $mainPlayer, "-", 1);
          AddDecisionQueue("PASSPARAMETER", $mainPlayer, "{0}", 1);
          AddDecisionQueue("MZREMOVE", $mainPlayer, "-", 1);
        }
        break;
      case "DYN159": case "DYN160": case "DYN161":
        if ($auras[$i] == "DYN159") $amount = 3;
        else if ($auras[$i] == "DYN160") $amount = 2;
        else $amount = 1;
        DestroyAuraUniqueID($mainPlayer, $auras[$i + 6]);
        Opt($auras[$i], $amount);
        AddDecisionQueue("BLESSINGOFFOCUS", $mainPlayer, "-", 1);
        break;
		  case "DYN179": case "DYN180": case "DYN181":
        if ($auras[$i] == "DYN179") $amount = 3;
        else if ($auras[$i] == "DYN180") $amount = 2;
        else $amount = 1;
        WriteLog(CardLink($auras[$i], $auras[$i]) . " create " . $amount . " Runechant.");
        PlayAura("ARC112", $mainPlayer, $amount, true);
        DestroyAuraUniqueID($mainPlayer, $auras[$i + 6]);
        break;
      case "DYN200": case "DYN201": case "DYN202":
        WriteLog(CardLink($auras[$i], $auras[$i]) . " buffs your next arcane damage card.");
        AddCurrentTurnEffect($auras[$i], $mainPlayer, "PLAY");
        DestroyAuraUniqueID($mainPlayer, $auras[$i + 6]);
        break;
      case "DYN218": case "DYN219": case "DYN220":
        if ($auras[$i] == "DYN218") $amount = 3;
        else if ($auras[$i] == "DYN219") $amount = 2;
        else $amount = 1;
        WriteLog(CardLink($auras[$i], $auras[$i]) . " creates " . $amount . " spectral shield tokens.");
        PlayAura("MON104", $mainPlayer, $amount);
        DestroyAuraUniqueID($mainPlayer, $auras[$i + 6]);
        break;
      case "DYN217":
        AddLayer("TRIGGER", $mainPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        break;
      default:
        break;
    }
    SetClassState($mainPlayer, $CS_EffectContext, "-");
  }
}


function AuraBeginEndPhaseAbilities()
{
  global $mainPlayer;
  $auras = &GetAuras($mainPlayer);
  for ($i = count($auras) - AuraPieces(); $i >= 0; $i -= AuraPieces()) {
    $remove = 0;
    $test = 0;
    switch ($auras[$i]) {
      case "ELE117":
        ++$auras[$i + 2];
        ChannelTalent($i, "EARTH");
        break;
      case "ELE146":
        ++$auras[$i + 2];
        ChannelTalent($i, "ICE");
        break;
      case "ELE175":
        ++$auras[$i + 2];
        ChannelTalent($i, "LIGHTNING");
        break;
      case "UPR005":
        ++$auras[$i + 2];
        $discard = &GetDiscard($mainPlayer);
        $leftToBanish = $auras[$i + 2];
        $numReds = 0;
        for ($j = 0; $j < count($discard); $j++) {
          if (PitchValue($discard[$j]) == 1) {
            ++$numReds;
          }
        }
        if ($leftToBanish <= $numReds) {
          AddDecisionQueue("PASSPARAMETER", $mainPlayer, $auras[$i + 2]);
          AddDecisionQueue("SETDQVAR", $mainPlayer, "0");
          for ($k = 0; $k < $auras[$i + 2]; $k++) {
            if ($leftToBanish > 1) $plurial = "cards";
            else $plurial = "card";
            AddDecisionQueue("MULTIZONEINDICES", $mainPlayer, "MYDISCARD:pitch=1;", 1);
            AddDecisionQueue("SETDQCONTEXT", $mainPlayer, "Choose " . $leftToBanish . " more " . $plurial . " to banish for Burn Them All", 1);
            AddDecisionQueue("MAYCHOOSEMULTIZONE", $mainPlayer, "<-", 1);
            AddDecisionQueue("MZBANISH", $mainPlayer, "GY,-," . $mainPlayer, 1);
            AddDecisionQueue("MZREMOVE", $mainPlayer, "-", 1);
            AddDecisionQueue("DECDQVAR", $mainPlayer, "0", 1);
            --$leftToBanish;
          }
          AddDecisionQueue("DESTROYCHANNEL", $mainPlayer, $i);
        } else {
          WriteLog(CardLink($auras[$i], $auras[$i]) . " was destroyed.");
          DestroyAura($mainPlayer, $i);
        }
        break;
      case "UPR138":
        ++$auras[$i + 2];
        ChannelTalent($i, "ICE");
        break;
      case "UPR176":
      case "UPR177":
      case "UPR178":
        if ($auras[$i] == "UPR176") $numOpt = 3;
        else if ($auras[$i] == "UPR177") $numOpt = 2;
        else $numOpt = 1;
        for ($j = 0; $j < $numOpt; ++$j) PlayerOpt($mainPlayer, 1);
        AddDecisionQueue("DRAW", $mainPlayer, "-", 1);
        $remove = 1;
        break;
      case "ELE111":
        FrostHexEndTurnAbility($mainPlayer);
        $remove = 1;
        break;
      case "DYN175":
        if ($auras[$i + 2] == 0) $remove = 1;
        else {
          --$auras[$i + 2];
          DealArcane(2, 2, "PLAYCARD", "DYN175", false, $mainPlayer);
        }
        break;
      case "DYN244":
        MyDrawCard();
        $remove = 1;
        break;
      default:
        break;
    }
    if ($remove == 1) DestroyAura($mainPlayer, $i);
  }
  $auras = array_values($auras);
}

function ChannelTalent($index, $talent)
{
  global $mainPlayer;
  $auras = &GetAuras($mainPlayer);
  $pitch = &GetPitch($mainPlayer);
  $leftToBottom = $auras[$index + 2];
  $numTalentInPitch = 0;

  for ($j = 0; $j < count($pitch); $j++) {
    if (TalentContains($pitch[$j], $talent, $mainPlayer) == 1) {
      ++$numTalentInPitch;
    }
  }
  if ($leftToBottom <= $numTalentInPitch) {
    AddDecisionQueue("PASSPARAMETER", $mainPlayer, $auras[$index + 2]);
    AddDecisionQueue("SETDQVAR", $mainPlayer, "0");
    for ($k = 0; $k < $auras[$index + 2]; $k++) {
      if ($leftToBottom > 1) $plurial = "cards";
      else $plurial = "card";
      AddDecisionQueue("MULTIZONEINDICES", $mainPlayer, "MYPITCH:talent=" . $talent . ";", 1);
      AddDecisionQueue("SETDQCONTEXT", $mainPlayer, "Choose " . $leftToBottom . " more " . $plurial . " to put at the bottom for " . CardName($auras[$index]), 1);
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $mainPlayer, "<-", 1);
      AddDecisionQueue("MZADDBOTDECK", $mainPlayer, "-", 1);
      AddDecisionQueue("MZREMOVE", $mainPlayer, "-", 1);
      AddDecisionQueue("DECDQVAR", $mainPlayer, "0", 1);
      --$leftToBottom;
    }
    AddDecisionQueue("DESTROYCHANNEL", $mainPlayer, $index);
  } else {
    WriteLog(CardLink($auras[$index], $auras[$index]) . " was destroyed.");
    DestroyAura($mainPlayer, $index);
  }
}

function AuraEndTurnAbilities()
{
  global $CS_NumNonAttackCards, $mainPlayer, $CS_HitsWithSword;
  $auras = &GetAuras($mainPlayer);
  for ($i = count($auras) - AuraPieces(); $i >= 0; $i -= AuraPieces()) {
    $remove = 0;
    switch ($auras[$i]) {
      case "ARC167": case "ARC168": case "ARC169":
        if (GetClassState($mainPlayer, $CS_NumNonAttackCards) == 0) {
          $remove = 1;
        }
        break;
      case "ELE226":
        $remove = 1;
        break;
      case "UPR139":
        $remove = 1;
        break;
      case "DYN072":
        if (GetClassState($mainPlayer, $CS_HitsWithSword) <= 0) {
          $remove = 1;
        }
        break;
      default:
        break;
    }
    if ($remove == 1) DestroyAura($mainPlayer, $i);
  }
}

function AuraEndTurnCleanup()
{
  $auras = &GetAuras(1);
  for ($i = 0; $i < count($auras); $i += AuraPieces()) {
    $auras[$i + 5] = AuraNumUses($auras[$i]);
  }
  $auras = &GetAuras(2);
  for ($i = 0; $i < count($auras); $i += AuraPieces()) {
    $auras[$i + 5] = AuraNumUses($auras[$i]);
  }
}

function AuraDamagePreventionAmount($player, $index)
{
  $auras = &GetAuras($player);
  switch($auras[$index])
  {
    case "ARC112": return (CountAura("CRU144", $player) > 0 ? 1 : 0);
    case "ARC167": return 4;
    case "ARC168": return 3;
    case "ARC169": return 2;
    case "MON104": return 1;
    case "UPR218": return 4;
    case "UPR219": return 3;
    case "UPR220": return 2;
    case "DYN217": return 1;
    case "DYN218": case "DYN219": case "DYN220": return 1;
    case "DYN221": case "DYN222": case "DYN223": return 1;
    default:
      break;
  }
}

//This function is for effects that prevent damage and DO destroy themselves
function AuraTakeDamageAbility($player, $index, $damage, $preventable)
{
  if ($preventable) $damage -= AuraDamagePreventionAmount($player, $index);
  DestroyAura($player, $index);
  return $damage;
}

//This function is for effects that prevent damage and do NOT destroy themselves
//These are applied first and not prompted (which would be annoying because of course you want to do this before consuming something)
function AuraTakeDamageAbilities($player, $damage, $type)
{
  $auras = &GetAuras($player);
  $otherPlayer = $player == 1 ? 1 : 2;
  //CR 2.1 6.4.10f If an effect states that a prevention effect can not prevent the damage of an event, the prevention effect still applies to the event but its prevention amount is not reduced. Any additional modifications to the event by the prevention effect still occur.
  $preventable = CanDamageBePrevented($otherPlayer, $damage, $type);
  for ($i = count($auras) - AuraPieces(); $i >= 0; $i -= AuraPieces()) {
    if ($damage <= 0) {
      $damage = 0;
      break;
    }
    switch ($auras[$i]) {
      case "CRU075":
        if ($preventable) $damage -= 1;
        break;
      case "EVR131":
        if ($type == "ARCANE" && $preventable) $damage -= 3;
        break;
      case "EVR132":
        if ($type == "ARCANE" && $preventable) $damage -= 2;
        break;
      case "EVR133":
        if ($type == "ARCANE" && $preventable) $damage -= 1;
        break;
      default:
        break;
    }
  }
  return $damage;
}


function AuraDamageTakenAbilities(&$Auras, $damage)
{
  for ($i = count($Auras) - AuraPieces(); $i >= 0; $i -= AuraPieces()) {
    $remove = 0;
    switch ($Auras[$i]) {
      case "ARC106":
      case "ARC107":
      case "ARC108":
        $remove = 1;
        break;
      case "EVR023":
        $remove = 1;
        break;
      default:
        break;
    }
    if ($remove == 1) {
      for ($j = $i + AuraPieces() - 1; $j >= $i; --$j) {
        unset($Auras[$j]);
      }
      $Auras = array_values($Auras);
    }
  }
  return $damage;
}

function AuraLoseHealthAbilities($player, $amount)
{
  global $mainPlayer;
  $auras = &GetAuras($player);
  for ($i = count($auras) - AuraPieces(); $i >= 0; $i -= AuraPieces()) {
    $remove = 0;
    switch ($auras[$i]) {
      case "MON157":
        if ($player == $mainPlayer) {
          $remove = 1;
        }
        break;
      default:
        break;
    }
    if ($remove == 1) DestroyAura($player, $i);
  }
  return $amount;
}

function AuraPlayAbilities($attackID, $from="")
{
  global $currentPlayer, $CS_NumIllusionistActionCardAttacks;
  $auras = &GetAuras($currentPlayer);
  $cardType = CardType($attackID);
  $cardSubType = CardSubType($attackID);
  for ($i = count($auras) - AuraPieces(); $i >= 0; $i -= AuraPieces()) {
    $remove = 0;
    switch ($auras[$i]) {
      case "WTR225":
        if ($cardType == "AA" || ($cardSubType == "Aura" && $from == "PLAY") || ($cardType == "W" && GetResolvedAbilityType($attackID) == "AA")) {
          WriteLog(CardLink($auras[$i], $auras[$i]) . " grants go again.");
          GiveAttackGoAgain();
          $remove = 1;
        }
        break;
      case "ARC112":
        if ($cardType == "AA"|| ($cardSubType == "Aura" && $from == "PLAY") || ($cardType == "W" && GetResolvedAbilityType($attackID) == "AA")) {
          $numRunechants = CountAura("ARC112", $currentPlayer);
          if ($cardType == "AA" && $numRunechants > 0) WriteLog($numRunechants . " total Runechant tokens trigger incoming arcane damage.");
          AddLayer("TRIGGER", $currentPlayer, $auras[$i], "-", "-", $auras[$i + 6]);
        }
        break;
      case "MON157":
        DimenxxionalCrossroadsPassive($attackID, $from);
        break;
      case "EVR143":
        if ($auras[$i + 5] > 0 && CardType($attackID) == "AA" && ClassContains($attackID, "ILLUSIONIST", $currentPlayer) && GetClassState($currentPlayer, $CS_NumIllusionistActionCardAttacks) <= 1) {
          WriteLog(CardLink($auras[$i], $auras[$i]) . " gives the attack +2.");
          --$auras[$i + 5];
          AddCurrentTurnEffect("EVR143", $currentPlayer, true);
        }
        break;
      case "ELE175":
        if ($cardType == "A" || $cardType == "AA") {
          AddLayer("TRIGGER", $currentPlayer, $auras[$i], $cardType, "-", $auras[$i + 6]);
        }
        break;
      default:
        break;
    }
  if ($remove == 1) DestroyAura($currentPlayer, $i);
  }
}

function AuraAttackAbilities($attackID)
{
  global $combatChain, $mainPlayer, $CS_PlayIndex, $CS_NumIllusionistAttacks;
  $auras = &GetAuras($mainPlayer);
  $attackType = CardType($attackID);
  for ($i = count($auras) - AuraPieces(); $i >= 0; $i -= AuraPieces()) {
    $remove = 0;
    switch ($auras[$i]) {
      case "ELE110":
        if ($attackType == "AA") {
          WriteLog(CardLink($auras[$i], $auras[$i]) . " grants go again.");
          GiveAttackGoAgain();
          $remove = 1;
        }
        break;
      case "ELE226":
        if ($attackType == "AA") DealArcane(1, 0, "PLAYCARD", $combatChain[0]);
        break;
      case "EVR140":
        if ($auras[$i + 5] > 0 && DelimStringContains(CardSubtype($attackID), "Aura") && ClassContains($attackID, "ILLUSIONIST", $mainPlayer)) {
          WriteLog(CardLink($auras[$i], $auras[$i]) . " puts a +1 counter.");
          --$auras[$i + 5];
          ++$auras[GetClassState($mainPlayer, $CS_PlayIndex) + 3];
        }
        break;
      case "EVR142":
        if ($auras[$i + 5] > 0 && ClassContains($attackID, "ILLUSIONIST", $mainPlayer) && GetClassState($mainPlayer, $CS_NumIllusionistAttacks) <= 1) {
          WriteLog(CardLink($auras[$i], $auras[$i]) . " makes your first illusionist attack each turn lose Phantasm.");
          --$auras[$i + 5];
          AddCurrentTurnEffect("EVR142", $mainPlayer, true);
        }
        break;
      case "UPR005":
        if ($auras[$i + 5] > 0 && DelimStringContains(CardSubType($attackID), "Dragon")) {
          --$auras[$i + 5];
          DealArcane(1, 1, "STATIC", $attackID, false, $mainPlayer);
        }
        break;
      default:
        break;
    }
    if ($remove == 1) DestroyAura($mainPlayer, $i);
  }
}

function AuraHitEffects($attackID)
{
  global $mainPlayer;
  $attackType = CardType($attackID);
  $attackSubType = CardSubType($attackID);
  $auras = &GetAuras($mainPlayer);
  for ($i = count($auras) - AuraPieces(); $i >= 0; $i -= AuraPieces()) {
    $remove = 0;
    switch ($auras[$i]) {
      case "ARC106":
        if ($attackType == "AA") {
          WriteLog(CardLink($auras[$i], $auras[$i]) . " created 3 runechants.");
          PlayAura("ARC112", $mainPlayer, 3);
          $remove = 1;
        }
        break;
      case "ARC107":
        if ($attackType == "AA") {
          WriteLog(CardLink($auras[$i], $auras[$i]) . " created 2 runechants.");
          PlayAura("ARC112", $mainPlayer, 2);
          $remove = 1;
        }
        break;
      case "ARC108":
        if ($attackType == "AA") {
          WriteLog(CardLink($auras[$i], $auras[$i]) . " created 1 runechants.");
          PlayAura("ARC112", $mainPlayer, 1);
          $remove = 1;
        }
        break;
      default:
        break;
    }
    if ($remove == 1) {
      for ($j = $i + AuraPieces() - 1; $j >= $i; --$j) {
        unset($auras[$j]);
      }
      $auras = array_values($auras);
    }
  }
}

function AuraAttackModifiers($index)
{
  global $combatChain;
  $modifier = 0;
  $player = $combatChain[$index + 1];
  $otherPlayer = ($player == 1 ? 2 : 1);
  $controlAuras = &GetAuras($player);
  for ($i = 0; $i < count($controlAuras); $i += AuraPieces()) {
    switch ($controlAuras[$i]) {
      case "ELE117":
        if (CardType($combatChain[$index]) == "AA") {
          $modifier += 3;
        }
        break;
      default:
        break;
    }
  }
  $otherAuras = &GetAuras($otherPlayer);
  for ($i = 0; $i < count($otherAuras); $i += AuraPieces()) {
    switch ($otherAuras[$i]) {
      case "MON011":
        if (CardType($combatChain[$index]) == "AA") {
          $modifier -= 1;
        }
        break;
      default:
        break;
    }
  }
  return $modifier;
}

function NumNonTokenAura($player)
{
  $count = 0;
  $auras = &GetAuras($player);
  for ($i = 0; $i < count($auras); $i += AuraPieces()) {
    if (CardType($auras[$i]) != "T") ++$count;
  }
  return $count;
}

function DestroyAllThisAura($player, $cardID)
{
  $auras = &GetAuras($player);
  $count = 0;
  for ($i = count($auras) - AuraPieces(); $i >= 0; $i -= AuraPieces()) {
    if ($auras[$i] == $cardID)
    {
      DestroyAura($player, $i);
      ++$count;
    }
  }
  return $count;
}

function GetAuraGemState($player, $cardID)
{
  global $currentPlayer;
  $auras = &GetAuras($player);
  $offset = ($currentPlayer == $player ? 7 : 8);
  $state = 0;
  for ($i = 0; $i < count($auras); $i += AuraPieces()) {
    if ($auras[$i] == $cardID && $auras[$i + $offset] > $state) $state = $auras[$i + $offset];
  }
  return $state;
}

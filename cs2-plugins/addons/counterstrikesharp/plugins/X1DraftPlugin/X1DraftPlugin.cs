using CounterStrikeSharp.API;
using CounterStrikeSharp.API.Core;
using CounterStrikeSharp.API.Modules.Commands;
using CounterStrikeSharp.API.Modules.Utils;

namespace X1DraftPlugin;

public sealed class X1DraftPlugin : BasePlugin
{
    public override string ModuleName => "X1 Draft MIX Plugin";
    public override string ModuleVersion => "1.0.0";
    public override string ModuleAuthor => "Andrei Kamada + ChatGPT";
    public override string ModuleDescription => "Draft de MIX 5v5 por X1 com picks, bans e troca automática de mapa.";

    private const int MinPlayersToStart = 4;
    private const int MaxTeamSize = 5;

    private readonly Random _random = new();

    private readonly List<string> _mapPool =
    [
        "de_mirage",
        "de_inferno",
        "de_nuke",
        "de_ancient",
        "de_anubis",
        "de_dust2",
        "de_cache"
    ];

    private bool _active;
    private bool _waitingPick;
    private bool _waitingBan;
    private bool _duelLocked;

    private int _targetTeamSize = MaxTeamSize;

    private int _captainA = -1;
    private int _captainB = -1;

    private readonly List<int> _teamA = [];
    private readonly List<int> _teamB = [];
    private readonly List<int> _available = [];

    private int _duelA = -1;
    private int _duelB = -1;

    private int _firstPickCaptain = -1;
    private int _secondPickCaptain = -1;
    private int _pickStep;

    private int _lastPickedA = -1;
    private int _lastPickedB = -1;

    private int _firstBanCaptain = -1;
    private int _secondBanCaptain = -1;
    private int _banTurnCaptain = -1;

    private readonly List<string> _remainingMaps = [];

    public override void Load(bool hotReload)
    {
        AddCommand("css_mix_start", "Inicia o draft MIX por X1 com capitães aleatórios", CmdMixStart);
        AddCommand("css_mix_startm", "Inicia o draft MIX por X1 com capitães manuais", CmdMixStartManual);
        AddCommand("css_mix_reset", "Reinicia o draft MIX por X1", CmdMixReset);
        AddCommand("css_mix_cancel", "Cancela o draft MIX por X1", CmdMixCancel);

        AddCommand("css_mix_pick", "Escolhe jogador disponível: !mix_pick <id>", CmdMixPick);
        AddCommand("css_p", "Atalho para escolher jogador: !p <id>", CmdMixPick);

        AddCommand("css_mix_ban", "Bane mapa disponível: !mix_ban <mapa ou id>", CmdMixBan);
        AddCommand("css_b", "Atalho para banir mapa: !b <mapa ou id>", CmdMixBan);

        RegisterEventHandler<EventPlayerDeath>(OnPlayerDeath);
        RegisterEventHandler<EventRoundStart>(OnRoundStart);
        RegisterEventHandler<EventPlayerDisconnect>(OnPlayerDisconnect);
    }

    private void CmdMixStart(CCSPlayerController? player, CommandInfo info)
    {
        if (_active)
        {
            Reply(info, "Já existe um MIX em andamento. Use !mix_reset ou !mix_cancel.");
            return;
        }

        StartMix(info);
    }

    private void CmdMixStartManual(CCSPlayerController? player, CommandInfo info)
    {
        if (_active)
        {
            Reply(info, "Já existe um MIX em andamento. Use !mix_reset ou !mix_cancel.");
            return;
        }

        if (info.ArgCount < 3)
        {
            Reply(info, "Uso correto: !mix_startm <idCapitaoA> <idCapitaoB>");
            ShowAllPlayerIds(info);
            return;
        }

        if (!int.TryParse(info.ArgByIndex(1), out var capA) ||
            !int.TryParse(info.ArgByIndex(2), out var capB))
        {
            Reply(info, "IDs inválidos. Use: !mix_startm <idCapitaoA> <idCapitaoB>");
            ShowAllPlayerIds(info);
            return;
        }

        if (capA == capB)
        {
            Reply(info, "Os capitães precisam ser jogadores diferentes.");
            return;
        }

        if (Find(capA) == null || Find(capB) == null)
        {
            Reply(info, "Um dos capitães não foi encontrado no servidor.");
            ShowAllPlayerIds(info);
            return;
        }

        StartMix(info, capA, capB);
    }

    private void CmdMixReset(CCSPlayerController? player, CommandInfo info)
    {
        CancelMix(false);
        StartMix(info);
    }

    private void CmdMixCancel(CCSPlayerController? player, CommandInfo info)
    {
        if (!_active)
        {
            Reply(info, "Não existe MIX em andamento.");
            return;
        }

        CancelMix(true);
    }

    private void StartMix(CommandInfo info, int? manualCaptainA = null, int? manualCaptainB = null)
    {
        var players = GetValidPlayers();

        if (players.Count < MinPlayersToStart)
        {
            Reply(info, $"Jogadores insuficientes. Mínimo para teste: {MinPlayersToStart}.");
            return;
        }

        ResetStateOnly();

        _active = true;
        _targetTeamSize = Math.Min(MaxTeamSize, players.Count / 2);

        PrintAll("======================================");
        PrintAll("[MIX X1] Evento iniciado!");
        PrintAll("[MIX X1] Movendo todos os jogadores para spectador...");
        PrintAll("======================================");

        foreach (var p in players)
            p.ChangeTeam(CsTeam.Spectator);

        AddTimer(2.0f, () =>
        {
            var currentPlayers = GetValidPlayers();

            if (currentPlayers.Count < MinPlayersToStart)
            {
                PrintAll("[MIX X1] Jogadores insuficientes após mover para spectador. MIX cancelado.");
                CancelMix(true);
                return;
            }

            if (manualCaptainA.HasValue && manualCaptainB.HasValue)
            {
                _captainA = manualCaptainA.Value;
                _captainB = manualCaptainB.Value;
            }
            else
            {
                var shuffled = currentPlayers.OrderBy(_ => _random.Next()).ToList();

                _captainA = shuffled[0].Slot;
                _captainB = shuffled[1].Slot;
            }

            if (Find(_captainA) == null || Find(_captainB) == null)
            {
                PrintAll("[MIX X1] Capitão não encontrado. MIX cancelado.");
                CancelMix(true);
                return;
            }

            _teamA.Add(_captainA);
            _teamB.Add(_captainB);

            _available.AddRange(
                currentPlayers
                    .Select(p => p.Slot)
                    .Where(slot => slot != _captainA && slot != _captainB)
            );

            PrintAll("======================================");
            PrintAll("[MIX X1] Capitães definidos!");
            PrintAll($"[MIX X1] Capitão A: {Name(_captainA)}");
            PrintAll($"[MIX X1] Capitão B: {Name(_captainB)}");
            PrintAll($"[MIX X1] Tamanho dos times neste teste: {_targetTeamSize}x{_targetTeamSize}");
            PrintAll("======================================");

            StartDuel(_captainA, _captainB, "X1 inicial entre os capitães.");
        });
    }

    private void CmdMixPick(CCSPlayerController? player, CommandInfo info)
    {
        if (!_active || !_waitingPick)
        {
            Reply(info, "Nenhum pick está disponível agora.");
            return;
        }

        if (player == null || !player.IsValid)
        {
            Reply(info, "Apenas capitães podem escolher jogadores.");
            return;
        }

        var captainSlot = player.Slot;

        if ((_pickStep == 1 && captainSlot != _firstPickCaptain) ||
            (_pickStep == 2 && captainSlot != _secondPickCaptain))
        {
            Reply(info, "Não é sua vez de escolher.");
            return;
        }

        if (info.ArgCount < 2)
        {
            Reply(info, "Uso correto: !mix_pick <id>");
            ShowAvailablePlayers(captainSlot);
            return;
        }

        if (!int.TryParse(info.ArgByIndex(1), out var chosenSlot) || !_available.Contains(chosenSlot))
        {
            Reply(info, "ID inválido ou jogador não está disponível.");
            ShowAvailablePlayers(captainSlot);
            return;
        }

        var captainTeam = GetCaptainTeam(captainSlot);

        if (captainTeam == 1)
            _teamA.Add(chosenSlot);
        else if (captainTeam == 2)
            _teamB.Add(chosenSlot);
        else
            return;

        _available.Remove(chosenSlot);

        if (captainSlot == _captainA)
            _lastPickedA = chosenSlot;
        else if (captainSlot == _captainB)
            _lastPickedB = chosenSlot;

        PrintAll($"[MIX X1] {Name(captainSlot)} escolheu {Name(chosenSlot)}.");

        if (_pickStep == 1)
        {
            _pickStep = 2;
            PrintAll($"[MIX X1] Agora é a vez de {Name(_secondPickCaptain)} escolher.");
            ShowAvailablePlayers(_secondPickCaptain);
            return;
        }

        _waitingPick = false;
        _pickStep = 0;

        PrintTeams();

        if (_teamA.Count >= _targetTeamSize && _teamB.Count >= _targetTeamSize)
        {
            StartFinalMapDuel();
            return;
        }

        if (_lastPickedA == -1 || _lastPickedB == -1)
        {
            PrintAll("[MIX X1] Erro: não foi possível definir o próximo X1.");
            CancelMix(true);
            return;
        }

        StartDuel(_lastPickedA, _lastPickedB, "X1 entre os jogadores escolhidos no round anterior.");
    }

    private void CmdMixBan(CCSPlayerController? player, CommandInfo info)
    {
        if (!_active || !_waitingBan)
        {
            Reply(info, "Nenhum ban de mapa está disponível agora.");
            return;
        }

        if (player == null || !player.IsValid || player.Slot != _banTurnCaptain)
        {
            Reply(info, "Não é sua vez de banir mapa.");
            return;
        }

        if (info.ArgCount < 2)
        {
            Reply(info, "Uso correto: !b <id> ou !b <mapa>");
            ShowMaps();
            return;
        }

        var arg = info.ArgByIndex(1).Trim().ToLowerInvariant();
        string map;

        if (int.TryParse(arg, out var mapId))
        {
            if (mapId < 1 || mapId > _remainingMaps.Count)
            {
                Reply(info, "ID de mapa inválido.");
                ShowMaps();
                return;
            }

            map = _remainingMaps[mapId - 1];
        }
        else
        {
            map = arg;
        }

        if (!_remainingMaps.Contains(map))
        {
            Reply(info, "Mapa inválido ou já banido.");
            ShowMaps();
            return;
        }

        _remainingMaps.Remove(map);

        PrintAll($"[MIX X1] {Name(player.Slot)} baniu {map}.");

        if (_remainingMaps.Count == 1)
        {
            var selectedMap = _remainingMaps[0];

            PrintAll("======================================");
            PrintAll($"[MIX X1] Mapa escolhido: {selectedMap}");
            PrintAll("[MIX X1] Executando comando de troca de mapa...");
            PrintAll("======================================");

            _waitingBan = false;

            AddTimer(2.0f, () =>
            {
                PrintAll($"[MIX X1] Trocando para o mapa {selectedMap}...");
                Server.ExecuteCommand($"changelevel {selectedMap}");
            });

            return;
        }

        _banTurnCaptain = _banTurnCaptain == _firstBanCaptain ? _secondBanCaptain : _firstBanCaptain;

        PrintAll($"[MIX X1] Próximo ban: {Name(_banTurnCaptain)}.");
        ShowMaps();
    }

    private HookResult OnRoundStart(EventRoundStart @event, GameEventInfo info)
    {
        if (!_active || _duelA == -1 || _duelB == -1)
            return HookResult.Continue;

        AddTimer(0.4f, PrepareDuelPlayers);
        return HookResult.Continue;
    }

    private HookResult OnPlayerDeath(EventPlayerDeath @event, GameEventInfo info)
    {
        if (!_active || _duelLocked)
            return HookResult.Continue;

        var victim = @event.Userid;

        if (victim == null || !victim.IsValid)
            return HookResult.Continue;

        if (victim.Slot != _duelA && victim.Slot != _duelB)
            return HookResult.Continue;

        var winner = victim.Slot == _duelA ? _duelB : _duelA;

        _duelLocked = true;

        AddTimer(1.0f, () => HandleDuelWinner(winner));

        return HookResult.Continue;
    }

    private HookResult OnPlayerDisconnect(EventPlayerDisconnect @event, GameEventInfo info)
    {
        if (!_active)
            return HookResult.Continue;

        var player = @event.Userid;

        if (player == null)
            return HookResult.Continue;

        if (player.Slot == _captainA || player.Slot == _captainB || player.Slot == _duelA || player.Slot == _duelB)
        {
            PrintAll("[MIX X1] Um jogador essencial saiu do servidor. MIX cancelado.");
            CancelMix(true);
        }

        return HookResult.Continue;
    }

    private void StartDuel(int playerA, int playerB, string reason)
    {
        _duelA = playerA;
        _duelB = playerB;
        _duelLocked = false;
        _waitingPick = false;
        _waitingBan = false;

        PrintAll("======================================");
        PrintAll($"[MIX X1] {reason}");
        PrintAll($"[MIX X1] X1: {Name(playerA)} vs {Name(playerB)}");
        PrintAll("[MIX X1] Armas: faca + Desert Eagle + colete/capacete.");
        PrintAll("======================================");

        MoveEveryoneToSpectatorExcept(playerA, playerB);

        AddTimer(0.5f, () =>
        {
            var pA = Find(playerA);
            var pB = Find(playerB);

            pA?.ChangeTeam(CsTeam.Terrorist);
            pB?.ChangeTeam(CsTeam.CounterTerrorist);

            Server.ExecuteCommand("mp_free_armor 2");
            Server.ExecuteCommand("mp_buytime 0");
            Server.ExecuteCommand("mp_roundtime 1.92");
            Server.ExecuteCommand("mp_restartgame 1");
        });
    }

    private void PrepareDuelPlayers()
    {
        var pA = Find(_duelA);
        var pB = Find(_duelB);

        PreparePlayer(pA);
        PreparePlayer(pB);

        MoveEveryoneToSpectatorExcept(_duelA, _duelB);
    }

    private void PreparePlayer(CCSPlayerController? player)
    {
        if (player == null || !player.IsValid)
            return;

        try
        {
            player.RemoveWeapons();
        }
        catch
        {
            Server.ExecuteCommand($"css_slay #{player.UserId}");
        }

        AddTimer(0.2f, () =>
        {
            if (player == null || !player.IsValid)
                return;

            player.GiveNamedItem("weapon_knife");
            player.GiveNamedItem("weapon_deagle");

            var pawn = player.PlayerPawn.Value;

            if (pawn != null && pawn.IsValid)
            {
                pawn.Health = 100;
                pawn.ArmorValue = 100;
            }
        });
    }

    private void HandleDuelWinner(int winner)
    {
        if (!_active)
            return;

        var loser = winner == _duelA ? _duelB : _duelA;

        PrintAll($"[MIX X1] Vencedor do X1: {Name(winner)}.");

        _duelA = -1;
        _duelB = -1;
        _duelLocked = false;

        if (_teamA.Count >= _targetTeamSize && _teamB.Count >= _targetTeamSize)
        {
            BeginMapBan(winner, loser);
            return;
        }

        var winnerCaptain = GetCaptainByPlayer(winner);
        var loserCaptain = GetCaptainByPlayer(loser);

        _firstPickCaptain = winnerCaptain;
        _secondPickCaptain = loserCaptain;
        _pickStep = 1;
        _waitingPick = true;

        PrintAll("======================================");
        PrintAll($"[MIX X1] {Name(_firstPickCaptain)} escolhe primeiro.");
        PrintAll($"[MIX X1] Depois será a vez de {Name(_secondPickCaptain)}.");
        PrintAll("Use: !p <id> ou !mix_pick <id>");
        PrintAll("======================================");

        ShowAvailablePlayers(_firstPickCaptain);
    }

    private void StartFinalMapDuel()
    {
        PrintAll("======================================");
        PrintAll("[MIX X1] Times completos.");
        PrintAll("[MIX X1] Últimos escolhidos vão disputar o X1 que define o primeiro ban.");
        PrintAll("======================================");

        if (_lastPickedA == -1 || _lastPickedB == -1)
        {
            BeginMapBan(_captainA, _captainB);
            return;
        }

        StartDuel(_lastPickedA, _lastPickedB, "X1 final para definir a ordem dos bans.");
    }

    private void BeginMapBan(int winner, int loser)
    {
        _remainingMaps.Clear();
        _remainingMaps.AddRange(_mapPool);

        _firstBanCaptain = GetCaptainByPlayer(winner);
        _secondBanCaptain = GetCaptainByPlayer(loser);
        _banTurnCaptain = _firstBanCaptain;

        _waitingBan = true;

        PrintAll("======================================");
        PrintAll("[MIX X1] Fase de bans iniciada.");
        PrintAll($"[MIX X1] Primeiro ban: {Name(_firstBanCaptain)}.");
        PrintAll("Use: !b <id> ou !mix_ban <mapa>");
        PrintAll("======================================");

        ShowMaps();
    }

    private void ShowAllPlayerIds(CommandInfo info)
    {
        Reply(info, "Jogadores disponíveis:");

        foreach (var p in GetValidPlayers())
            Reply(info, $"ID {p.Slot}: {p.PlayerName}");
    }

    private void MoveEveryoneToSpectatorExcept(int slotA, int slotB)
    {
        foreach (var player in GetValidPlayers())
        {
            if (player.Slot == slotA || player.Slot == slotB)
                continue;

            player.ChangeTeam(CsTeam.Spectator);
        }
    }

    private int GetCaptainByPlayer(int slot)
    {
        if (_teamA.Contains(slot))
            return _captainA;

        if (_teamB.Contains(slot))
            return _captainB;

        return _captainA;
    }

    private int GetCaptainTeam(int captainSlot)
    {
        if (captainSlot == _captainA)
            return 1;

        if (captainSlot == _captainB)
            return 2;

        return 0;
    }

    private void PrintTeams()
    {
        PrintAll("======================================");
        PrintAll($"[MIX X1] Time A / Capitão {Name(_captainA)}:");
        foreach (var slot in _teamA)
            PrintAll($"- {Name(slot)}");

        PrintAll($"[MIX X1] Time B / Capitão {Name(_captainB)}:");
        foreach (var slot in _teamB)
            PrintAll($"- {Name(slot)}");
        PrintAll("======================================");
    }

    private void ShowAvailablePlayers(int captainSlot)
    {
        var captain = Find(captainSlot);

        if (_available.Count == 0)
        {
            captain?.PrintToChat(" [MIX X1] Nenhum jogador disponível.");
            return;
        }

        captain?.PrintToChat(" [MIX X1] Jogadores disponíveis:");

        foreach (var slot in _available)
            captain?.PrintToChat($" [MIX X1] ID {slot}: {Name(slot)}");
    }

    private void ShowMaps()
    {
        PrintAll("[MIX X1] Mapas restantes:");

        for (int i = 0; i < _remainingMaps.Count; i++)
            PrintAll($"ID {i + 1}: {_remainingMaps[i]}");

        PrintAll("[MIX X1] Use: !b <id> ou !b <mapa>");
    }

    private List<CCSPlayerController> GetValidPlayers()
    {
        return Utilities.GetPlayers()
            .Where(p =>
                p != null &&
                p.IsValid &&
                !p.IsBot &&
                p.UserId.HasValue)
            .ToList();
    }

    private CCSPlayerController? Find(int slot)
    {
        return Utilities.GetPlayers()
            .FirstOrDefault(p => p != null && p.IsValid && p.Slot == slot);
    }

    private string Name(int slot)
    {
        var player = Find(slot);
        return player == null ? $"Jogador#{slot}" : player.PlayerName;
    }

    private void PrintAll(string message)
    {
        Server.PrintToChatAll($" {message}");
        Console.WriteLine(message);
    }

    private void Reply(CommandInfo info, string message)
    {
        info.ReplyToCommand($"[MIX X1] {message}");
    }

    private void CancelMix(bool announce)
    {
        if (announce)
            PrintAll("[MIX X1] Evento cancelado.");

        ResetStateOnly();

        Server.ExecuteCommand("mp_buytime 20");
        Server.ExecuteCommand("mp_free_armor 0");
    }

    private void ResetStateOnly()
    {
        _active = false;
        _waitingPick = false;
        _waitingBan = false;
        _duelLocked = false;

        _targetTeamSize = MaxTeamSize;

        _captainA = -1;
        _captainB = -1;

        _teamA.Clear();
        _teamB.Clear();
        _available.Clear();

        _duelA = -1;
        _duelB = -1;

        _firstPickCaptain = -1;
        _secondPickCaptain = -1;
        _pickStep = 0;

        _lastPickedA = -1;
        _lastPickedB = -1;

        _firstBanCaptain = -1;
        _secondBanCaptain = -1;
        _banTurnCaptain = -1;

        _remainingMaps.Clear();
    }
}
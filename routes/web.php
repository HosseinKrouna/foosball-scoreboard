<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/elo.php';

function finish_match(PDO $pdo, int $matchId): array {
    // hol Match
    $stmt = $pdo->prepare("SELECT id, team_a_id, team_b_id, score_a, score_b, status FROM matches WHERE id=? LIMIT 1");
    $stmt->execute([$matchId]);
    $m = $stmt->fetch();
    if (!$m) return ['ok'=>false,'error'=>'not_found'];
    if (($m['status'] ?? '') === 'finished') return ['ok'=>true,'status'=>'finished'];

    $teamA = (int)$m['team_a_id'];
    $teamB = (int)$m['team_b_id'];
    $scoreA= (int)$m['score_a'];
    $scoreB= (int)$m['score_b'];

    $pdo->beginTransaction();
    try {
        // stats
        $pdo->prepare("UPDATE teams SET games_played=games_played+1 WHERE id IN (?,?)")->execute([$teamA,$teamB]);
        if ($scoreA > $scoreB) {
            $pdo->prepare("UPDATE teams SET wins=wins+1 WHERE id=?")->execute([$teamA]);
        } elseif ($scoreB > $scoreA) {
            $pdo->prepare("UPDATE teams SET wins=wins+1 WHERE id=?")->execute([$teamB]);
        }
        // elo
        $stmt = $pdo->prepare("SELECT id, rating FROM teams WHERE id IN (?, ?) FOR UPDATE");
        $stmt->execute([$teamA,$teamB]);
        $rows = $stmt->fetchAll();
        $ratings=[]; foreach($rows as $r) $ratings[(int)$r['id']] = (int)$r['rating'];
        [$raNew,$rbNew] = elo_update($ratings[$teamA]??1500, $ratings[$teamB]??1500, $scoreA, $scoreB);
        $pdo->prepare("UPDATE teams SET rating=? WHERE id=?")->execute([$raNew,$teamA]);
        $pdo->prepare("UPDATE teams SET rating=? WHERE id=?")->execute([$rbNew,$teamB]);

        // finish
        $pdo->prepare("UPDATE matches SET status='finished', finished_at=NOW() WHERE id=?")->execute([$matchId]);

        $pdo->commit();
        return ['ok'=>true,'status'=>'finished','score_a'=>$scoreA,'score_b'=>$scoreB];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok'=>false,'error'=>'finish_failed','message'=>$e->getMessage()];
    }
}

function route(string $method, string $path): string {
    // Home
    if ($method === 'GET' && $path === '/') {
        $title = 'Foosball Scoreboard';
        ob_start(); include __DIR__ . '/../views/home.php';
        return (string)ob_get_clean();
    }

    // Leaderboard
    if ($method === 'GET' && $path === '/leaderboard') {
        $title = 'Leaderboard';
        $pdo = db();
        $stmt = $pdo->query("SELECT id,name,rating,wins,games_played FROM teams ORDER BY rating DESC,wins DESC,games_played DESC LIMIT 50");
        $teams = $stmt->fetchAll();
        ob_start(); include __DIR__ . '/../views/leaderboard.php';
        return (string)ob_get_clean();
    }

    // New match form
    if ($method === 'GET' && $path === '/match/new') {
        $title = 'New Match';
        $pdo = db();
        $teams = $pdo->query("SELECT id,name FROM teams ORDER BY name ASC")->fetchAll();
        $errors=[]; $old=[];
        ob_start(); include __DIR__ . '/../views/match_new.php';
        return (string)ob_get_clean();
    }

    // Create match (0:0, in_progress, target_score)
    if ($method === 'POST' && $path === '/match') {
        $pdo = db();
        $mode  = $_POST['mode'] ?? '';
        $teamA = isset($_POST['team_a_id']) ? (int)$_POST['team_a_id'] : 0;
        $teamB = isset($_POST['team_b_id']) ? (int)$_POST['team_b_id'] : 0;
        $notes = trim((string)($_POST['notes'] ?? ''));
        $target = (int)($_POST['target_score'] ?? 10);

        $errors = [];
        if (!in_array($mode,['1v1','2v2'],true)) $errors[]='Invalid mode.';
        if ($teamA<=0 || $teamB<=0) $errors[]='Both teams are required.';
        if ($teamA===$teamB) $errors[]='Team A and Team B must be different.';
        if ($target < 1 || $target > 50) $errors[]='Target score must be between 1 and 50.';

        if (empty($errors)) {
            $stmt=$pdo->prepare("SELECT COUNT(*) c FROM teams WHERE id IN (?, ?)");
            $stmt->execute([$teamA,$teamB]);
            if ((int)$stmt->fetch()['c'] !== 2) $errors[]='Unknown team selected.';
        }

        if (!empty($errors)) {
            $title='New Match';
            $teams=$pdo->query("SELECT id,name FROM teams ORDER BY name ASC")->fetchAll();
            $old=['mode'=>$mode,'team_a_id'=>$teamA,'team_b_id'=>$teamB,'notes'=>$notes,'target_score'=>$target];
            ob_start(); include __DIR__ . '/../views/match_new.php';
            return (string)ob_get_clean();
        }

        $stmt=$pdo->prepare("
          INSERT INTO matches (mode,team_a_id,team_b_id,score_a,score_b,target_score,notes,status)
          VALUES (?, ?, ?, 0, 0, ?, ?, 'in_progress')
        ");
        $stmt->execute([$mode,$teamA,$teamB,$target,$notes!==''?$notes:null]);
        $matchId=(int)$pdo->lastInsertId();
        header('Location: /match/'.$matchId.'?created=1'); exit;
    }

    // TV view
    if ($method === 'GET' && preg_match('#^/match/(\d+)$#',$path,$m)) {
        $matchId=(int)$m[1]; $pdo=db();
        $stmt=$pdo->prepare("
          SELECT m.id,m.played_at,m.mode,m.team_a_id,m.team_b_id,m.score_a,m.score_b,m.target_score,
                 m.notes,m.status,m.finished_at,
                 a.name AS team_a_name,a.rating AS rating_a,
                 b.name AS team_b_name,b.rating AS rating_b
          FROM matches m
          JOIN teams a ON a.id=m.team_a_id
          JOIN teams b ON b.id=m.team_b_id
          WHERE m.id=? LIMIT 1
        ");
        $stmt->execute([$matchId]); $match=$stmt->fetch();
        if(!$match){ http_response_code(404); return 'Match not found'; }

        $isInProgress=(($match['status']??'in_progress')!=='finished');
        $title='Match #'.$matchId;
        $created=isset($_GET['created'])&&$_GET['created']==='1';
        $finishedMsg=isset($_GET['finished'])&&$_GET['finished']==='1';
        $err=$_GET['err']??null;

        ob_start(); include __DIR__ . '/../views/match_show.php';
        return (string)ob_get_clean();
    }

    // ===== JSON API =====

    // GET state
    if ($method==='GET' && preg_match('#^/api/match/(\d+)$#',$path,$m)){
        $matchId=(int)$m[1]; $pdo=db();
        $stmt=$pdo->prepare("SELECT id,score_a,score_b,target_score,status FROM matches WHERE id=? LIMIT 1");
        $stmt->execute([$matchId]); $row=$stmt->fetch();
        header('Content-Type: application/json; charset=utf-8');
        if(!$row){ http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
        echo json_encode(['ok'=>true]+$row); exit;
    }

    // POST score +/-  (OHNE Auto-Finish; capped bis target_score; liefert reached-Flag)
    if ($method==='POST' && preg_match('#^/api/match/(\d+)/score$#',$path,$m)){
        $matchId=(int)$m[1]; $pdo=db();
        header('Content-Type: application/json; charset=utf-8');

        $team=strtoupper(substr((string)($_POST['team']??''),0,1));
        $delta=(int)($_POST['delta']??0);
        if($delta>1)$delta=1; if($delta<-1)$delta=-1;
        if(!in_array($team,['A','B'],true) || $delta===0){
            http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_input']); exit;
        }

        $col = ($team==='A') ? 'score_a' : 'score_b';

        // Score anpassen, capped: 0..target_score, nur wenn in_progress/NULL
        $stmt=$pdo->prepare("UPDATE matches
                             SET $col = LEAST(target_score, GREATEST(0, $col + ?))
                             WHERE id=? AND (status='in_progress' OR status IS NULL)");
        $stmt->execute([$delta,$matchId]);
        if($stmt->rowCount()===0){
            http_response_code(409); echo json_encode(['ok'=>false,'error'=>'not_in_progress']); exit;
        }

        // Neuen Stand + target holen
        $stmt=$pdo->prepare("SELECT score_a,score_b,target_score,status FROM matches WHERE id=? LIMIT 1");
        $stmt->execute([$matchId]); $row=$stmt->fetch();

        // Reached-Flag berechnen (ohne zu finishen)
        $reached = ((int)$row['score_a'] >= (int)$row['target_score']) || ((int)$row['score_b'] >= (int)$row['target_score']);
        $leader = null;
        if ((int)$row['score_a'] > (int)$row['score_b']) $leader = 'A';
        elseif ((int)$row['score_b'] > (int)$row['score_a']) $leader = 'B';
        else $leader = 'tie';

        echo json_encode([
            'ok'=>true,
            'match_id'=>$matchId,
            'score_a'=>(int)$row['score_a'],
            'score_b'=>(int)$row['score_b'],
            'target_score'=>(int)$row['target_score'],
            'status'=>$row['status'],
            'reached'=>$reached,
            'leader'=>$leader,
        ]);
        exit;
    }

    // POST finish (manuell)
    if ($method==='POST' && preg_match('#^/api/match/(\d+)/finish$#',$path,$m)){
        $matchId=(int)$m[1]; $pdo=db();
        header('Content-Type: application/json; charset=utf-8');
        $res = finish_match($pdo, $matchId);
        if ($res['ok'] ?? false) { echo json_encode($res); exit; }
        http_response_code(($res['error']??'')==='not_found'?404:500);
        echo json_encode($res); exit;
    }

    http_response_code(404); return 'Not found';
}
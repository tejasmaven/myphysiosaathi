"""Exercise the durable reference allocator using independent PHP processes."""
import concurrent.futures
import os
from pathlib import Path
import shlex
import subprocess
import tempfile

root = Path(__file__).resolve().parents[1]
php = shlex.split(os.environ.get('PHP_COMMAND', 'php'))
code = """
require $argv[1].'/assessment/report-store.php';
$_SERVER['DOCUMENT_ROOT']=$argv[1];
echo assessment_allocate_reference(['report_directory'=>$argv[2]],new DateTimeImmutable($argv[3]));
"""
with tempfile.TemporaryDirectory(prefix='mps-reference-') as directory:
    def allocate(date='2026-10-09T12:00:00+05:30'):
        return subprocess.check_output(
            php + ['-r', code, str(root), directory, date], text=True).strip()

    with concurrent.futures.ThreadPoolExecutor(max_workers=8) as executor:
        refs = list(executor.map(lambda _: allocate(), range(24)))
    assert set(refs) == {f'09102026-{i}' for i in range(1, 25)}
    assert allocate() == '09102026-25'
    assert allocate('2026-10-09T20:00:00+00:00') == '10102026-1'
    # Corrupt counters must fail rather than reset and reuse a previous number.
    Path(directory, 'reference-09102026.seq').write_text('invalid')
    result = subprocess.run(php + ['-r', code, str(root), directory,
                            '2026-10-09T12:00:00+05:30'], capture_output=True)
    assert result.returncode != 0
print('PASS: 24 concurrent unique daily references, durable sequence, India midnight and corrupt-counter rejection.')

import argparse
import csv
import math
import os
import struct
import time
from dataclasses import dataclass
from datetime import date, datetime
from pathlib import Path
from typing import Dict, Iterable, List, Optional, Sequence, Set, Tuple

from pymodbus.client import ModbusTcpClient


@dataclass(frozen=True)
class TagDefinition:
    name: str
    plc_addr: int
    data_type: str
    source: str
    word_count: int


@dataclass
class DailyKgTracker:
    day: date
    hourly_totals: Dict[int, float]
    active_hours: Set[int]
    active_runtime_seconds: float


TAGS: List[TagDefinition] = [
    TagDefinition("UN1_AKIS", 202, "float32", "holding", 2),
    TagDefinition("UN1_ALT_KLEPE_KAPAT_SURESI", 214, "int16", "holding", 1),
    TagDefinition("UN1_ALT_SENSOR", 2103, "bool", "coil", 1),
    TagDefinition("UN1_AYLIK", 206, "float32", "holding", 2),
    TagDefinition("UN1_AYLIK_SIFIRLA", 2071, "bool", "coil", 1),
    TagDefinition("UN1_BOS_KALIBRASYON", 2023, "bool", "coil", 1),
    TagDefinition("UN1_GUNLUK", 204, "float32", "holding", 2),
    TagDefinition("UN1_GUNLUK_SIFIRLA", 2070, "bool", "coil", 1),
    TagDefinition("UN1_KG", 200, "float32", "holding", 2),
    TagDefinition("UN1_KG_BAYKON", 0, "int16", "holding", 1),
    TagDefinition("UN1_LD_DEGERI", 3840, "float64", "holding", 4),
    TagDefinition("UN1_PARTI_KG_SIFIRLA", 2065, "bool", "coil", 1),
    TagDefinition("UN1_SET_DEGERI", 210, "float32", "holding", 2),
    TagDefinition("UN1_STABIL_SURESI", 212, "int16", "holding", 1),
    TagDefinition("UN1_START_SURESI", 216, "int16", "holding", 1),
    TagDefinition("UN1_TOPLAM_1", 204, "float32", "holding", 2),
    TagDefinition("UN1_TOPLAM_2", 208, "float32", "holding", 2),
    TagDefinition("UN1_TOPLAM_DUN", 150, "float32", "holding", 2),
    TagDefinition("UN1_YILLIK", 208, "float32", "holding", 2),
    TagDefinition("UN1_YILLIK_SIFIRLA", 2072, "bool", "coil", 1),
    TagDefinition("UN1_YUK_DEGERI", 226, "float32", "holding", 2),
    TagDefinition("UN1_YUK_KALIBRASYON", 2024, "bool", "coil", 1),
]


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="UN1 PLC reader: live terminal output + CSV logging"
    )
    parser.add_argument("--ip", default="192.168.20.103", help="PLC IP address")
    parser.add_argument("--port", type=int, default=502, help="Modbus TCP port")
    parser.add_argument("--slave", type=int, default=1, help="Modbus slave/unit id")
    parser.add_argument(
        "--interval", type=float, default=0.5, help="Polling interval in seconds"
    )
    parser.add_argument(
        "--csv", default="un1_log.csv", help="CSV output path (default: un1_log.csv)"
    )
    parser.add_argument(
        "--addr-shift",
        type=int,
        default=0,
        help="Address shift for PLC mapping (example: -1 or +1)",
    )
    parser.add_argument(
        "--timeout", type=float, default=3.0, help="Connection timeout in seconds"
    )
    parser.add_argument(
        "--daily-report",
        default="un1_daily_report.csv",
        help="Daily report CSV path (default: un1_daily_report.csv)",
    )
    parser.add_argument(
        "--work-threshold",
        type=float,
        default=0.1,
        help="UN1_KG threshold for counting active runtime (default: 0.1)",
    )
    parser.add_argument(
        "--kg-max",
        type=float,
        default=100000.0,
        help="Ignore UN1_KG values above this limit in hourly/daily sum (default: 100000)",
    )
    return parser.parse_args()


def clear_screen() -> None:
    os.system("cls" if os.name == "nt" else "clear")


def signed_int16(value: int) -> int:
    return value - 65536 if value > 32767 else value


def _try_unpack_float32(words: Sequence[int], order: str) -> Optional[float]:
    if len(words) != 2:
        return None

    w0, w1 = words
    if order == "ABCD":
        payload = struct.pack(">HH", w0, w1)
    elif order == "CDAB":
        payload = struct.pack(">HH", w1, w0)
    elif order == "BADC":
        payload = struct.pack(">HH", ((w0 & 0xFF) << 8) | (w0 >> 8), ((w1 & 0xFF) << 8) | (w1 >> 8))
    elif order == "DCBA":
        payload = struct.pack(">HH", ((w1 & 0xFF) << 8) | (w1 >> 8), ((w0 & 0xFF) << 8) | (w0 >> 8))
    else:
        return None

    try:
        return struct.unpack(">f", payload)[0]
    except struct.error:
        return None


def _is_reasonable_float(value: float, limit: float) -> bool:
    return math.isfinite(value) and abs(value) < limit


def decode_float32_auto(words: Sequence[int]) -> Optional[float]:
    preferred = ["ABCD", "CDAB", "BADC", "DCBA"]
    candidates: List[Tuple[str, float]] = []

    for order in preferred:
        unpacked = _try_unpack_float32(words, order)
        if unpacked is not None:
            candidates.append((order, unpacked))

    if not candidates:
        return None

    for _, value in candidates:
        if _is_reasonable_float(value, limit=1e9):
            return value

    for _, value in candidates:
        if math.isfinite(value):
            return value

    return None


def _try_unpack_float64(words: Sequence[int], reverse_words: bool, swap_bytes: bool) -> Optional[float]:
    if len(words) != 4:
        return None

    selected = list(words[::-1] if reverse_words else words)
    if swap_bytes:
        selected = [((w & 0xFF) << 8) | (w >> 8) for w in selected]

    payload = struct.pack(">HHHH", *selected)
    try:
        return struct.unpack(">d", payload)[0]
    except struct.error:
        return None


def decode_float64_auto(words: Sequence[int]) -> Optional[float]:
    combos = [
        (False, False),
        (True, False),
        (False, True),
        (True, True),
    ]
    candidates: List[float] = []

    for reverse_words, swap_bytes in combos:
        unpacked = _try_unpack_float64(words, reverse_words, swap_bytes)
        if unpacked is not None:
            candidates.append(unpacked)

    if not candidates:
        return None

    for value in candidates:
        if _is_reasonable_float(value, limit=1e12):
            return value

    for value in candidates:
        if math.isfinite(value):
            return value

    return None


def build_read_blocks(
    tags: Iterable[TagDefinition],
    source: str,
    addr_shift: int,
    max_span: int,
    max_gap: int,
) -> List[Tuple[int, int]]:
    spans: List[Tuple[int, int]] = []

    for tag in tags:
        if tag.source != source:
            continue
        start = tag.plc_addr + addr_shift
        length = tag.word_count if source == "holding" else 1
        end = start + length - 1
        spans.append((start, end))

    spans.sort(key=lambda item: item[0])
    if not spans:
        return []

    blocks: List[Tuple[int, int]] = []
    current_start, current_end = spans[0]

    for span_start, span_end in spans[1:]:
        gap = span_start - current_end - 1
        merged_end = max(current_end, span_end)
        merged_span = merged_end - current_start + 1

        if gap <= max_gap and merged_span <= max_span:
            current_end = merged_end
            continue

        blocks.append((current_start, current_end - current_start + 1))
        current_start, current_end = span_start, span_end

    blocks.append((current_start, current_end - current_start + 1))
    return blocks


def _call_modbus_read(method, address: int, count: int, slave: int):
    # pymodbus signatures differ across versions (device_id/slave/unit).
    attempts = (
        {"address": address, "count": count, "device_id": slave},
        {"address": address, "count": count, "slave": slave},
        {"address": address, "count": count, "unit": slave},
        {"address": address, "count": count, "slave_id": slave},
    )

    last_error: Optional[TypeError] = None
    for kwargs in attempts:
        try:
            return method(**kwargs)
        except TypeError as exc:
            last_error = exc

    # Extra positional fallback for uncommon legacy wrappers.
    try:
        return method(address, count, slave)
    except TypeError as exc:
        last_error = exc

    raise last_error if last_error else TypeError("No compatible Modbus read signature found.")


def _read_holding_registers(
    client: ModbusTcpClient, address: int, count: int, slave: int
):
    return _call_modbus_read(client.read_holding_registers, address, count, slave)


def _read_coils(client: ModbusTcpClient, address: int, count: int, slave: int):
    return _call_modbus_read(client.read_coils, address, count, slave)


def read_holding_blocks(
    client: ModbusTcpClient, blocks: Sequence[Tuple[int, int]], slave: int
) -> Tuple[Dict[int, int], List[str]]:
    values: Dict[int, int] = {}
    errors: List[str] = []

    for start, count in blocks:
        result = _read_holding_registers(client, address=start, count=count, slave=slave)
        if result.isError():
            errors.append(f"Holding block read failed (start={start}, count={count})")
            continue

        registers = getattr(result, "registers", None)
        if not registers:
            errors.append(f"Holding block empty response (start={start}, count={count})")
            continue

        for index, value in enumerate(registers[:count]):
            values[start + index] = value

    return values, errors


def read_coil_blocks(
    client: ModbusTcpClient, blocks: Sequence[Tuple[int, int]], slave: int
) -> Tuple[Dict[int, int], List[str]]:
    values: Dict[int, int] = {}
    errors: List[str] = []

    for start, count in blocks:
        result = _read_coils(client, address=start, count=count, slave=slave)
        if result.isError():
            errors.append(f"Coil block read failed (start={start}, count={count})")
            continue

        bits = getattr(result, "bits", None)
        if bits is None:
            errors.append(f"Coil block empty response (start={start}, count={count})")
            continue

        for index, value in enumerate(bits[:count]):
            values[start + index] = 1 if value else 0

    return values, errors


def decode_tag(
    tag: TagDefinition,
    holding_values: Dict[int, int],
    coil_values: Dict[int, int],
    addr_shift: int,
) -> Tuple[Optional[float], Optional[str]]:
    start = tag.plc_addr + addr_shift

    if tag.source == "coil":
        bit = coil_values.get(start)
        if bit is None:
            return None, "coil-missing"
        return int(bit), None

    words = [holding_values.get(start + i) for i in range(tag.word_count)]
    if any(word is None for word in words):
        return None, "holding-missing"

    clean_words = [int(word) for word in words if word is not None]

    try:
        if tag.data_type == "int16":
            return signed_int16(clean_words[0]), None
        if tag.data_type == "float32":
            value = decode_float32_auto(clean_words)
            if value is None:
                return None, "float32-decode"
            return value, None
        if tag.data_type == "float64":
            value = decode_float64_auto(clean_words)
            if value is None:
                return None, "float64-decode"
            return value, None
        if tag.data_type == "bool":
            return int(clean_words[0] != 0), None

        return None, f"unknown-type:{tag.data_type}"
    except Exception as exc:  # keep a single tag failure from stopping the loop
        return None, f"decode-exception:{exc}"


def ensure_csv_header(csv_path: Path, tags: Sequence[TagDefinition]) -> None:
    csv_path.parent.mkdir(parents=True, exist_ok=True)
    if csv_path.exists() and csv_path.stat().st_size > 0:
        return

    with csv_path.open("a", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerow(["timestamp", *[tag.name for tag in tags]])


def init_daily_tracker(day_value: date) -> DailyKgTracker:
    return DailyKgTracker(
        day=day_value,
        hourly_totals={hour: 0.0 for hour in range(24)},
        active_hours=set(),
        active_runtime_seconds=0.0,
    )


def ensure_daily_report_header(report_path: Path) -> None:
    report_path.parent.mkdir(parents=True, exist_ok=True)
    if report_path.exists() and report_path.stat().st_size > 0:
        return

    with report_path.open("a", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerow(
            [
                "report_day",
                "closed_at",
                "close_reason",
                "kg_total_sum",
                "active_hours_count",
                "active_runtime_hours",
                "hourly_breakdown",
            ]
        )


def format_hourly_breakdown(hourly_totals: Dict[int, float]) -> str:
    return " | ".join(f"{hour:02d}:{hourly_totals.get(hour, 0.0):.3f}" for hour in range(24))


def append_daily_report_row(
    report_path: Path,
    tracker: DailyKgTracker,
    closed_at: datetime,
    close_reason: str,
) -> None:
    kg_total_sum = sum(tracker.hourly_totals.values())
    active_runtime_hours = tracker.active_runtime_seconds / 3600.0

    with report_path.open("a", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerow(
            [
                tracker.day.isoformat(),
                closed_at.isoformat(timespec="seconds"),
                close_reason,
                round(kg_total_sum, 6),
                len(tracker.active_hours),
                round(active_runtime_hours, 6),
                format_hourly_breakdown(tracker.hourly_totals),
            ]
        )


def update_daily_tracker(
    tracker: DailyKgTracker,
    timestamp: datetime,
    kg_value: Optional[float],
    interval_seconds: float,
    work_threshold: float,
    kg_max: float,
) -> None:
    if kg_value is None or not math.isfinite(float(kg_value)):
        return

    numeric_value = float(kg_value)
    if numeric_value < 0 or numeric_value > kg_max:
        return

    if numeric_value > 0:
        tracker.hourly_totals[timestamp.hour] = tracker.hourly_totals.get(timestamp.hour, 0.0) + numeric_value

    if numeric_value >= work_threshold:
        tracker.active_hours.add(timestamp.hour)
        tracker.active_runtime_seconds += max(0.0, interval_seconds)


def append_csv_row(
    csv_path: Path,
    timestamp: datetime,
    tags: Sequence[TagDefinition],
    decoded: Dict[str, Optional[float]],
) -> None:
    row: List[object] = [timestamp.isoformat(timespec="seconds")]

    for tag in tags:
        value = decoded.get(tag.name)
        if value is None:
            row.append("ERR")
        else:
            row.append(value)

    with csv_path.open("a", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerow(row)


def render_terminal(
    timestamp: datetime,
    args: argparse.Namespace,
    tags: Sequence[TagDefinition],
    decoded: Dict[str, Optional[float]],
    decode_errors: Dict[str, str],
    read_errors: Sequence[str],
    daily_tracker: Optional[DailyKgTracker],
) -> None:
    clear_screen()
    print("=" * 88)
    print("UN1 PLC CANLI IZLEME")
    print("=" * 88)
    print(
        f"Time: {timestamp.strftime('%Y-%m-%d %H:%M:%S')} | PLC: {args.ip}:{args.port} | "
        f"Slave: {args.slave} | Interval: {args.interval}s | AddrShift: {args.addr_shift}"
    )
    print("-" * 88)
    print(f"{'TAG':30} {'TYPE':9} {'SRC':8} {'ADDR':8} {'VALUE':26}")
    print("-" * 88)

    for tag in tags:
        value = decoded.get(tag.name)
        if value is None:
            value_text = "ERR"
        elif tag.data_type in {"float32", "float64"}:
            value_text = f"{value:.6f}"
        else:
            value_text = str(int(value))

        addr_text = str(tag.plc_addr + args.addr_shift)
        print(f"{tag.name:30} {tag.data_type:9} {tag.source:8} {addr_text:8} {value_text:26}")

    print("-" * 88)
    print(f"CSV: {Path(args.csv).resolve()}")
    if daily_tracker is not None:
        hourly_total = daily_tracker.hourly_totals.get(timestamp.hour, 0.0)
        daily_total = sum(daily_tracker.hourly_totals.values())
        runtime_hours = daily_tracker.active_runtime_seconds / 3600.0
        print(
            f"KG SUMMARY -> ThisHour: {hourly_total:.3f} | Today: {daily_total:.3f} | "
            f"ActiveHours: {len(daily_tracker.active_hours)} | Runtime: {runtime_hours:.2f}h"
        )
        print(f"DAILY REPORT CSV: {Path(args.daily_report).resolve()}")

    if read_errors:
        print("READ ERRORS:")
        for err in read_errors:
            print(f"- {err}")

    if decode_errors:
        print("DECODE ERRORS:")
        for tag_name, err in decode_errors.items():
            print(f"- {tag_name}: {err}")

    print("Ctrl+C ile cikis yapabilirsiniz.")


def validate_addresses(tags: Sequence[TagDefinition], addr_shift: int) -> None:
    negatives = [tag for tag in tags if tag.plc_addr + addr_shift < 0]
    if negatives:
        names = ", ".join(tag.name for tag in negatives)
        raise ValueError(f"Negative address produced with --addr-shift={addr_shift}: {names}")


def run_loop(args: argparse.Namespace) -> None:
    validate_addresses(TAGS, args.addr_shift)

    holding_blocks = build_read_blocks(
        TAGS, source="holding", addr_shift=args.addr_shift, max_span=120, max_gap=8
    )
    coil_blocks = build_read_blocks(
        TAGS, source="coil", addr_shift=args.addr_shift, max_span=256, max_gap=16
    )

    csv_path = Path(args.csv)
    ensure_csv_header(csv_path, TAGS)
    report_path = Path(args.daily_report)
    ensure_daily_report_header(report_path)
    daily_tracker = init_daily_tracker(datetime.now().date())

    client = ModbusTcpClient(host=args.ip, port=args.port, timeout=args.timeout)
    if not client.connect():
        raise ConnectionError(
            f"PLC connection failed: {args.ip}:{args.port} (slave={args.slave})"
        )

    try:
        while True:
            cycle_started = time.perf_counter()
            now = datetime.now()
            if now.date() != daily_tracker.day:
                append_daily_report_row(
                    report_path,
                    tracker=daily_tracker,
                    closed_at=now,
                    close_reason="day-rollover",
                )
                daily_tracker = init_daily_tracker(now.date())

            holding_values, holding_errors = read_holding_blocks(
                client, blocks=holding_blocks, slave=args.slave
            )
            coil_values, coil_errors = read_coil_blocks(
                client, blocks=coil_blocks, slave=args.slave
            )

            decoded: Dict[str, Optional[float]] = {}
            decode_errors: Dict[str, str] = {}

            for tag in TAGS:
                value, error = decode_tag(
                    tag,
                    holding_values=holding_values,
                    coil_values=coil_values,
                    addr_shift=args.addr_shift,
                )
                decoded[tag.name] = value
                if error is not None:
                    decode_errors[tag.name] = error

            update_daily_tracker(
                tracker=daily_tracker,
                timestamp=now,
                kg_value=decoded.get("UN1_KG"),
                interval_seconds=args.interval,
                work_threshold=args.work_threshold,
                kg_max=args.kg_max,
            )

            append_csv_row(csv_path, timestamp=now, tags=TAGS, decoded=decoded)

            render_terminal(
                timestamp=now,
                args=args,
                tags=TAGS,
                decoded=decoded,
                decode_errors=decode_errors,
                read_errors=[*holding_errors, *coil_errors],
                daily_tracker=daily_tracker,
            )

            elapsed = time.perf_counter() - cycle_started
            sleep_for = max(0.0, args.interval - elapsed)
            time.sleep(sleep_for)
    finally:
        append_daily_report_row(
            report_path,
            tracker=daily_tracker,
            closed_at=datetime.now(),
            close_reason="shutdown",
        )
        client.close()


def main() -> None:
    args = parse_args()

    try:
        run_loop(args)
    except KeyboardInterrupt:
        print("\nReader stopped by user.")
    except Exception as exc:
        print(f"Error: {exc}")


if __name__ == "__main__":
    main()

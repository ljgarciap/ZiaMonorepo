"""Tests for history format normalization between Mistral and Anthropic formats."""
import json
import sys
import os

import pytest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
from main import normalize_history_for_anthropic, normalize_history_for_mistral  # noqa: E402


# ─── normalize_history_for_anthropic ─────────────────────────────────────────

class TestNormalizeForAnthropic:

    def test_simple_text_messages_pass_through(self):
        messages = [
            {"role": "user",      "content": "Hola"},
            {"role": "assistant", "content": "¡Hola! ¿En qué puedo ayudarte?"},
        ]
        result = normalize_history_for_anthropic(messages)
        assert result == messages

    def test_mistral_tool_calls_become_tool_use_blocks(self):
        messages = [
            {
                "role":    "assistant",
                "content": "",
                "tool_calls": [
                    {
                        "id":   "tc_001",
                        "type": "function",
                        "function": {
                            "name":      "get_company_profile",
                            "arguments": json.dumps({"company_id": 1}),
                        },
                    }
                ],
            }
        ]
        result = normalize_history_for_anthropic(messages)

        assert len(result) == 1
        msg = result[0]
        assert msg["role"] == "assistant"
        assert isinstance(msg["content"], list)
        block = msg["content"][0]
        assert block["type"] == "tool_use"
        assert block["id"] == "tc_001"
        assert block["name"] == "get_company_profile"
        assert block["input"] == {"company_id": 1}

    def test_mistral_tool_calls_with_text_includes_text_block(self):
        messages = [
            {
                "role":    "assistant",
                "content": "Voy a buscar el perfil.",
                "tool_calls": [
                    {
                        "id":   "tc_002",
                        "type": "function",
                        "function": {"name": "get_questionnaire", "arguments": '{"sector_code": "servicios"}'},
                    }
                ],
            }
        ]
        result = normalize_history_for_anthropic(messages)
        blocks = result[0]["content"]
        types = [b["type"] for b in blocks]
        assert "text" in types
        assert "tool_use" in types

    def test_single_tool_role_message_becomes_user_tool_result(self):
        messages = [
            {
                "role":         "tool",
                "content":      '{"name": "ECONOVA"}',
                "tool_call_id": "tc_001",
                "name":         "get_company_profile",
            }
        ]
        result = normalize_history_for_anthropic(messages)

        assert len(result) == 1
        msg = result[0]
        assert msg["role"] == "user"
        assert isinstance(msg["content"], list)
        block = msg["content"][0]
        assert block["type"] == "tool_result"
        assert block["tool_use_id"] == "tc_001"
        assert block["content"] == '{"name": "ECONOVA"}'

    def test_consecutive_tool_role_messages_grouped_into_one_user_message(self):
        messages = [
            {"role": "tool", "content": "result_1", "tool_call_id": "tc_a", "name": "tool_a"},
            {"role": "tool", "content": "result_2", "tool_call_id": "tc_b", "name": "tool_b"},
        ]
        result = normalize_history_for_anthropic(messages)

        # Must collapse into a SINGLE user message with two tool_result blocks
        assert len(result) == 1
        assert result[0]["role"] == "user"
        assert len(result[0]["content"]) == 2
        ids = [b["tool_use_id"] for b in result[0]["content"]]
        assert "tc_a" in ids
        assert "tc_b" in ids

    def test_full_mistral_turn_cycle(self):
        """User → assistant (tool_call) → tool result → assistant (text)"""
        messages = [
            {"role": "user", "content": "¿Qué emisiones hay?"},
            {
                "role": "assistant", "content": "",
                "tool_calls": [{"id": "tc_1", "type": "function",
                                "function": {"name": "get_pending_questions",
                                             "arguments": '{"company_id":1,"period_id":2,"sector_code":"servicios"}'}}],
            },
            {"role": "tool", "content": '{"pending": []}', "tool_call_id": "tc_1", "name": "get_pending_questions"},
            {"role": "assistant", "content": "El inventario está completo."},
        ]
        result = normalize_history_for_anthropic(messages)

        assert len(result) == 4
        assert result[0]["role"] == "user"
        assert result[1]["role"] == "assistant"
        assert result[1]["content"][0]["type"] == "tool_use"
        assert result[2]["role"] == "user"
        assert result[2]["content"][0]["type"] == "tool_result"
        assert result[3]["role"] == "assistant"
        assert result[3]["content"] == "El inventario está completo."

    def test_already_anthropic_format_passes_through(self):
        """If history is already in Anthropic format, it must not be double-converted."""
        messages = [
            {"role": "assistant", "content": [{"type": "tool_use", "id": "tu_1",
                                               "name": "calculate_ghg", "input": {"emission_factor_id": 5}}]},
            {"role": "user",      "content": [{"type": "tool_result", "tool_use_id": "tu_1",
                                               "content": '{"calculated_co2e": 2.5}'}]},
        ]
        result = normalize_history_for_anthropic(messages)
        # Must remain identical — no re-processing of Anthropic-format blocks
        assert result == messages

    def test_empty_history_returns_empty(self):
        assert normalize_history_for_anthropic([]) == []


# ─── normalize_history_for_mistral ────────────────────────────────────────────

class TestNormalizeForMistral:

    def test_simple_text_messages_pass_through(self):
        messages = [
            {"role": "user",      "content": "Hola"},
            {"role": "assistant", "content": "¿En qué puedo ayudarte?"},
        ]
        result = normalize_history_for_mistral(messages)
        assert result == messages

    def test_anthropic_tool_use_blocks_become_tool_calls(self):
        messages = [
            {
                "role":    "assistant",
                "content": [
                    {"type": "tool_use", "id": "tu_001", "name": "calculate_ghg",
                     "input": {"emission_factor_id": 3, "monthly_values": [100.0]}},
                ],
            }
        ]
        result = normalize_history_for_mistral(messages)

        assert len(result) == 1
        msg = result[0]
        assert msg["role"] == "assistant"
        assert "tool_calls" in msg
        tc = msg["tool_calls"][0]
        assert tc["id"] == "tu_001"
        assert tc["function"]["name"] == "calculate_ghg"
        parsed = json.loads(tc["function"]["arguments"])
        assert parsed["emission_factor_id"] == 3

    def test_anthropic_tool_result_blocks_become_separate_tool_messages(self):
        messages = [
            {
                "role":    "user",
                "content": [
                    {"type": "tool_result", "tool_use_id": "tu_001", "content": '{"calculated_co2e": 1.5}'},
                    {"type": "tool_result", "tool_use_id": "tu_002", "content": '{"id": 99}'},
                ],
            }
        ]
        result = normalize_history_for_mistral(messages)

        # Each tool_result block becomes its own "tool" role message
        assert len(result) == 2
        assert all(m["role"] == "tool" for m in result)
        ids = [m["tool_call_id"] for m in result]
        assert "tu_001" in ids
        assert "tu_002" in ids

    def test_anthropic_text_block_in_assistant_becomes_content_string(self):
        messages = [
            {
                "role":    "assistant",
                "content": [{"type": "text", "text": "Aquí está el resultado."}],
            }
        ]
        result = normalize_history_for_mistral(messages)

        assert len(result) == 1
        assert result[0]["role"] == "assistant"
        assert result[0]["content"] == "Aquí está el resultado."
        assert "tool_calls" not in result[0]

    def test_full_anthropic_turn_cycle(self):
        """User → assistant (tool_use) → user (tool_result) → assistant (text)"""
        messages = [
            {"role": "user", "content": "Calcula mis emisiones"},
            {
                "role":    "assistant",
                "content": [{"type": "tool_use", "id": "tu_1", "name": "calculate_ghg",
                             "input": {"emission_factor_id": 5, "monthly_values": [200.0]}}],
            },
            {
                "role":    "user",
                "content": [{"type": "tool_result", "tool_use_id": "tu_1",
                             "content": '{"calculated_co2e": 0.7}'}],
            },
            {"role": "assistant", "content": "Tus emisiones son 0.7 tCO2e."},
        ]
        result = normalize_history_for_mistral(messages)

        assert len(result) == 4
        assert result[0] == {"role": "user", "content": "Calcula mis emisiones"}
        assert result[1]["role"] == "assistant"
        assert result[1]["tool_calls"][0]["id"] == "tu_1"
        assert result[2]["role"] == "tool"
        assert result[2]["tool_call_id"] == "tu_1"
        assert result[3] == {"role": "assistant", "content": "Tus emisiones son 0.7 tCO2e."}

    def test_already_mistral_format_passes_through(self):
        """If history is already in Mistral format, it must not be double-converted."""
        messages = [
            {
                "role": "assistant", "content": "",
                "tool_calls": [{"id": "tc_x", "type": "function",
                                "function": {"name": "save_emission", "arguments": "{}"}}],
            },
            {"role": "tool", "content": '{"id": 42}', "tool_call_id": "tc_x", "name": "save_emission"},
        ]
        result = normalize_history_for_mistral(messages)
        assert result == messages

    def test_empty_history_returns_empty(self):
        assert normalize_history_for_mistral([]) == []


# ─── round-trip: Mistral → Anthropic → Mistral ───────────────────────────────

class TestRoundTrip:

    def test_tool_call_round_trip_mistral_to_anthropic_to_mistral(self):
        """A Mistral tool_call converted to Anthropic and back must be semantically equivalent."""
        original_mistral = [
            {
                "role": "assistant", "content": "",
                "tool_calls": [
                    {"id": "tc_rt", "type": "function",
                     "function": {"name": "get_company_profile",
                                  "arguments": json.dumps({"company_id": 7})}},
                ],
            }
        ]
        # Mistral → Anthropic
        as_anthropic = normalize_history_for_anthropic(original_mistral)
        # Anthropic → Mistral
        back_to_mistral = normalize_history_for_mistral(as_anthropic)

        assert back_to_mistral[0]["role"] == "assistant"
        tc = back_to_mistral[0]["tool_calls"][0]
        assert tc["id"] == "tc_rt"
        assert tc["function"]["name"] == "get_company_profile"
        assert json.loads(tc["function"]["arguments"]) == {"company_id": 7}

/**
 * ZiaChatComponent spec — 9 tests
 *
 * The component uses the Fetch API with SSE (Server-Sent Events) streaming.
 * All tests mock globalThis.fetch via vi.stubGlobal and restore it with
 * vi.unstubAllGlobals() in afterEach.
 */
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { vi } from 'vitest';

import { ZiaChatComponent } from './zia-chat';
import { AuthService } from '../../services/auth';
import { createMockAuthService } from '../../../testing/mocks';

// ---------------------------------------------------------------------------
// SSE test helpers
// ---------------------------------------------------------------------------

/**
 * Build a fake SSE Response whose body is a ReadableStream that delivers
 * `lines` as a single chunk, then closes.
 *
 * Each element in `lines` should be a full SSE line WITHOUT the trailing \n
 * (the helper appends one).  E.g.:
 *   'data: {"type":"text","content":"Hello"}'
 *   'data: {"type":"done"}'
 */
function makeSSEResponse(lines: string[]): Response {
  const combined = lines.map(l => l + '\n').join('') + '\n';
  const stream = new ReadableStream({
    start(controller) {
      controller.enqueue(new TextEncoder().encode(combined));
      controller.close();
    },
  });
  return new Response(stream, {
    status: 200,
    headers: { 'Content-Type': 'text/event-stream' },
  });
}

/** Flush all pending microtasks and one macrotask tick so async stream
 *  processing has time to complete.                                     */
const flushAsync = () => new Promise<void>(resolve => setTimeout(resolve, 0));

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('ZiaChatComponent', () => {
  let component: ZiaChatComponent;
  let fixture: ComponentFixture<ZiaChatComponent>;
  let authMock: ReturnType<typeof createMockAuthService>;

  beforeEach(async () => {
    authMock = createMockAuthService();
    // Default: authenticated company context so companyId is non-null
    authMock.currentContext.set({
      type: 'company',
      id: 42,
      label: 'ECONOVA',
      role: 'user',
    });

    await TestBed.configureTestingModule({
      imports: [ZiaChatComponent, NoopAnimationsModule],
      providers: [{ provide: AuthService, useValue: authMock }],
    }).compileComponents();

    fixture = TestBed.createComponent(ZiaChatComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.clearAllMocks();
  });

  // 1 — smoke test
  it('should create', () => {
    expect(component).toBeTruthy();
  });

  // 2 — toggle opens
  it('toggle() opens the chat panel', () => {
    expect(component.open()).toBe(false);
    component.toggle();
    expect(component.open()).toBe(true);
  });

  // 3 — toggle closes
  it('toggle() closes the chat when already open', () => {
    component.toggle(); // open
    component.toggle(); // close
    expect(component.open()).toBe(false);
  });

  // 4 — welcome message on first open
  it('opening chat for the first time adds a welcome message', () => {
    expect(component.messages().length).toBe(0);
    component.toggle();
    expect(component.messages().length).toBe(1);
    expect(component.messages()[0].role).toBe('assistant');
    expect(component.messages()[0].text).toContain('ZIA');
  });

  // 5 — null companyId shows error without calling fetch
  it('sendMessage() shows error message and does NOT call fetch when companyId is null', () => {
    authMock.currentContext.set(null); // no company context → companyId = null

    const fetchSpy = vi.fn();
    vi.stubGlobal('fetch', fetchSpy);

    component.inputText = '¿Cuánto CO2 emito?';
    component.sendMessage();

    // streamChat returns early (synchronously) when companyId is null
    expect(fetchSpy).not.toHaveBeenCalled();

    // An assistant error message must have been pushed (after the user message)
    const assistantMessages = component.messages().filter(m => m.role === 'assistant');
    expect(assistantMessages.length).toBeGreaterThan(0);
    expect(assistantMessages[0].text).toContain('empresa');
  });

  // 6 — onKeyDown Enter calls sendMessage
  it('onKeyDown with Enter triggers sendMessage()', () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(makeSSEResponse([
      'data: {"type":"done"}',
    ])));

    const spy = vi.spyOn(component, 'sendMessage');
    component.inputText = 'Hola';
    component.onKeyDown(new KeyboardEvent('keydown', { key: 'Enter', shiftKey: false }));

    expect(spy).toHaveBeenCalledTimes(1);
  });

  // 7 — onKeyDown Shift+Enter does NOT call sendMessage
  it('onKeyDown with Shift+Enter does NOT trigger sendMessage()', () => {
    const spy = vi.spyOn(component, 'sendMessage');
    component.inputText = 'Hola';
    component.onKeyDown(new KeyboardEvent('keydown', { key: 'Enter', shiftKey: true }));

    expect(spy).not.toHaveBeenCalled();
  });

  // 8 — SSE text event updates the pending message
  it('successful SSE stream with {type:"text"} event updates the message content', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(makeSSEResponse([
      'data: {"type":"text","content":"Hola desde ZIA"}',
      'data: {"type":"done"}',
    ])));

    component.inputText = 'test';
    component.sendMessage();

    await flushAsync();
    fixture.detectChanges();

    // After streaming completes the assistant message must contain the text
    const assistantMessages = component.messages().filter(m => m.role === 'assistant');
    expect(assistantMessages.length).toBeGreaterThan(0);
    const finalMsg = assistantMessages[assistantMessages.length - 1];
    expect(finalMsg.text).toContain('Hola desde ZIA');
    expect(finalMsg.pending).toBeFalsy();
  });

  // 9 — {type:"done"} finalizes the pending message (pending flag removed)
  it('{type:"done"} event marks the pending assistant message as finalized', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(makeSSEResponse([
      'data: {"type":"text","content":"Respuesta final"}',
      'data: {"type":"done"}',
    ])));

    component.inputText = 'dame un resumen';
    component.sendMessage();

    await flushAsync();
    fixture.detectChanges();

    // All messages must have pending=false after done event
    const allMessages = component.messages();
    expect(allMessages.every(m => !m.pending)).toBe(true);
    expect(component.streaming()).toBe(false);
  });

  // 10 (spec test 9) — clearChat resets messages and history
  it('clearChat() resets messages and pushes a restart assistant message', () => {
    // First send a couple of messages so there is something to clear
    component.toggle(); // adds welcome message
    component.clearChat();

    // After clearChat a single welcome-restart message is shown
    expect(component.messages().length).toBe(1);
    expect(component.messages()[0].role).toBe('assistant');
    expect(component.messages()[0].text).toContain('reiniciado');
  });
});

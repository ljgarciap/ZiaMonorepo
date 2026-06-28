import {
  Component, OnDestroy, signal,
  ViewChild, ElementRef, AfterViewChecked, inject
} from '@angular/core';
import { CommonModule }       from '@angular/common';
import { FormsModule }        from '@angular/forms';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { MatButtonModule }    from '@angular/material/button';
import { MatIconModule }      from '@angular/material/icon';
import { MatTooltipModule }   from '@angular/material/tooltip';
import { AuthService }        from '../../services/auth';
import { environment }        from '../../../environments/environment';

interface ChatMessage {
  role: 'user' | 'assistant';
  text: string;
  html: SafeHtml;
  toolActivity?: string;
  pending?: boolean;
}

interface HistoryEntry {
  role: 'user' | 'assistant';
  content: string;
}

@Component({
  selector: 'app-zia-chat',
  standalone: true,
  imports: [CommonModule, FormsModule, MatButtonModule, MatIconModule, MatTooltipModule],
  templateUrl: './zia-chat.html',
  styleUrls: ['./zia-chat.css'],
})
export class ZiaChatComponent implements OnDestroy, AfterViewChecked {
  @ViewChild('messagesEnd') private messagesEnd!: ElementRef;

  private sanitizer = inject(DomSanitizer);
  private auth      = inject(AuthService);

  open      = signal(false);
  messages  = signal<ChatMessage[]>([]);
  inputText = '';
  streaming = signal(false);
  activeTool= signal<string | null>(null);

  private history: HistoryEntry[] = [];
  private abortController: AbortController | null = null;
  private shouldScroll = false;

  get companyId(): number | null {
    return this.auth.currentContext()?.id ?? null;
  }

  toggle() {
    this.open.update(v => !v);
    if (this.open() && this.messages().length === 0) {
      this.pushAssistant('¡Hola! Soy ZIA, tu asistente de huella de carbono. ¿En qué te puedo ayudar hoy?');
    }
  }

  sendMessage() {
    const text = this.inputText.trim();
    if (!text || this.streaming()) return;

    this.inputText = '';
    this.pushUser(text);
    this.streamChat(text);
  }

  onKeyDown(event: KeyboardEvent) {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      this.sendMessage();
    }
  }

  private async streamChat(message: string) {
    if (!this.companyId) {
      this.pushAssistant('No hay una empresa seleccionada en el contexto. Por favor selecciona una empresa primero.');
      return;
    }

    this.streaming.set(true);
    this.abortController = new AbortController();

    // Add a pending assistant message that we'll fill in
    const pending: ChatMessage = {
      role: 'assistant',
      text: '',
      html: '',
      pending: true,
    };
    this.messages.update(msgs => [...msgs, pending]);
    const pendingIndex = this.messages().length - 1;

    try {
      const token = this.auth.getToken();
      const response = await fetch(`${environment.apiUrl}/ai/chat`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
          'X-Company-Context': String(this.companyId),
          'Accept': 'text/event-stream',
        },
        body: JSON.stringify({
          message,
          company_id: this.companyId,
          history: this.history,
        }),
        signal: this.abortController.signal,
      });

      if (!response.ok || !response.body) {
        throw new Error(`HTTP ${response.status}`);
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';
      let accumulatedText = '';

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop() ?? '';

        for (const line of lines) {
          if (!line.startsWith('data: ')) continue;
          const raw = line.slice(6).trim();
          if (!raw) continue;

          try {
            const event = JSON.parse(raw);
            if (event.type === 'text') {
              accumulatedText += event.content;
              this.updatePendingMessage(pendingIndex, accumulatedText);
            } else if (event.type === 'tool_start') {
              this.activeTool.set(this.toolLabel(event.tool));
            } else if (event.type === 'tool_end') {
              this.activeTool.set(null);
            } else if (event.type === 'done') {
              break;
            } else if (event.type === 'error') {
              accumulatedText += `\n\n⚠️ ${event.message}`;
              this.updatePendingMessage(pendingIndex, accumulatedText);
            }
          } catch {
            // ignore unparseable lines
          }
        }
      }

      // Finalize
      this.messages.update(msgs => {
        const updated = [...msgs];
        updated[pendingIndex] = {
          ...updated[pendingIndex],
          pending: false,
          text: accumulatedText,
          html: this.toSafeHtml(accumulatedText),
        };
        return updated;
      });

      // Update history for next turn
      this.history.push({ role: 'user', content: message });
      this.history.push({ role: 'assistant', content: accumulatedText });

      // Keep history to last 20 entries to avoid token overflows
      if (this.history.length > 20) {
        this.history = this.history.slice(-20);
      }

    } catch (err: any) {
      if (err?.name !== 'AbortError') {
        this.updatePendingMessage(pendingIndex, 'Hubo un error al conectar con el agente. Por favor intenta de nuevo.');
      }
      this.messages.update(msgs => {
        const updated = [...msgs];
        if (updated[pendingIndex]) updated[pendingIndex].pending = false;
        return updated;
      });
    } finally {
      this.streaming.set(false);
      this.activeTool.set(null);
      this.shouldScroll = true;
    }
  }

  private updatePendingMessage(index: number, text: string) {
    this.messages.update(msgs => {
      const updated = [...msgs];
      updated[index] = {
        ...updated[index],
        text,
        html: this.toSafeHtml(text),
      };
      return updated;
    });
    this.shouldScroll = true;
  }

  private pushUser(text: string) {
    this.messages.update(msgs => [...msgs, {
      role: 'user',
      text,
      html: this.sanitizer.bypassSecurityTrustHtml(this.escapeHtml(text).replace(/\n/g, '<br>')),
    }]);
    this.history.push({ role: 'user', content: text });
    this.shouldScroll = true;
  }

  private pushAssistant(text: string) {
    this.messages.update(msgs => [...msgs, {
      role: 'assistant',
      text,
      html: this.toSafeHtml(text),
    }]);
    this.shouldScroll = true;
  }

  private toSafeHtml(text: string): SafeHtml {
    // Minimal markdown: bold, inline code, code blocks, newlines
    let html = this.escapeHtml(text)
      .replace(/```[\s\S]*?```/g, m => {
        const code = m.slice(3, -3).replace(/^\w+\n/, '');
        return `<pre><code>${code}</code></pre>`;
      })
      .replace(/`([^`]+)`/g, '<code>$1</code>')
      .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
      .replace(/\*([^*]+)\*/g, '<em>$1</em>')
      .replace(/\n/g, '<br>');
    return this.sanitizer.bypassSecurityTrustHtml(html);
  }

  private escapeHtml(text: string): string {
    return text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  private toolLabel(tool: string): string {
    const labels: Record<string, string> = {
      get_company_profile:   'Leyendo perfil de empresa',
      get_questionnaire:     'Cargando cuestionario',
      get_emission_factors:  'Consultando factores',
      calculate_ghg:         'Calculando emisiones GHG',
      save_emission:         'Guardando registro',
      get_pending_questions: 'Revisando pendientes',
    };
    return labels[tool] ?? tool;
  }

  autoResize(event: Event) {
    const el = event.target as HTMLTextAreaElement;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
  }

  stopStreaming() {
    this.abortController?.abort();
  }

  clearChat() {
    this.messages.set([]);
    this.history = [];
    this.pushAssistant('Chat reiniciado. ¿En qué te puedo ayudar?');
  }

  ngAfterViewChecked() {
    if (this.shouldScroll) {
      this.messagesEnd?.nativeElement?.scrollIntoView({ behavior: 'smooth' });
      this.shouldScroll = false;
    }
  }

  ngOnDestroy() {
    this.abortController?.abort();
  }
}

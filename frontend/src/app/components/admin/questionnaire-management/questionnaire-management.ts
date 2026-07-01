import { Component, inject, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatDialogModule, MatDialog } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatOptionModule } from '@angular/material/core';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatChipsModule } from '@angular/material/chips';
import { MatDividerModule } from '@angular/material/divider';
import { MatExpansionModule } from '@angular/material/expansion';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatMenuModule } from '@angular/material/menu';
import { AdminService } from '../../../services/admin.service';

const STATUS_LABEL: Record<string, { label: string; color: string; icon: string }> = {
    draft:     { label: 'Borrador',   color: '#64748b', icon: 'edit_note' },
    published: { label: 'Publicada',  color: '#16a34a', icon: 'check_circle' },
    archived:  { label: 'Archivada',  color: '#94a3b8', icon: 'archive' },
};

const QUESTION_TYPES = [
    { value: 'text',        label: 'Texto libre' },
    { value: 'number',      label: 'Numero' },
    { value: 'select',      label: 'Seleccion unica' },
    { value: 'multiselect', label: 'Seleccion multiple' },
    { value: 'boolean',     label: 'Si / No' },
    { value: 'file',        label: 'Archivo adjunto' },
];

const SCOPE_HINTS = [
    { value: '',        label: 'Cualquiera' },
    { value: 'scope_1', label: 'Alcance 1' },
    { value: 'scope_2', label: 'Alcance 2' },
    { value: 'scope_3', label: 'Alcance 3' },
];

@Component({
    selector: 'app-questionnaire-management',
    standalone: true,
    imports: [
        CommonModule,
        FormsModule,
        ReactiveFormsModule,
        MatTableModule,
        MatButtonModule,
        MatIconModule,
        MatDialogModule,
        MatFormFieldModule,
        MatInputModule,
        MatSelectModule,
        MatOptionModule,
        MatTooltipModule,
        MatProgressSpinnerModule,
        MatChipsModule,
        MatDividerModule,
        MatExpansionModule,
        MatSlideToggleModule,
        MatMenuModule,
    ],
    template: `
    <div class="management-page">
      <div class="header-section">
        <div class="title-group">
          <h1>Cuestionarios Smart Intake</h1>
          <p class="subtitle">Editor de plantillas de captura guiada de datos de emisiones.</p>
        </div>
        <button mat-flat-button class="btn-prestige" (click)="onCreateTemplate()">
          <mat-icon>add</mat-icon> Nueva Plantilla
        </button>
      </div>

      <!-- Lista de plantillas -->
      <div *ngIf="!selectedTemplate" class="templates-grid">
        <div class="spinner-wrap" *ngIf="loading">
          <mat-spinner diameter="40"></mat-spinner>
        </div>

        <div *ngIf="!loading && templates.length === 0" class="empty-card glass-card">
          <mat-icon>quiz</mat-icon>
          <p>No hay plantillas aun. Crea la primera.</p>
        </div>

        <div *ngFor="let t of templates" class="template-card glass-card" (click)="openTemplate(t)">
          <div class="tc-header">
            <div class="tc-status-dot" [style.background]="statusFor(t.status).color"></div>
            <span class="tc-status-label" [style.color]="statusFor(t.status).color">
              <mat-icon style="font-size:14px;width:14px;height:14px;vertical-align:middle">{{statusFor(t.status).icon}}</mat-icon>
              {{statusFor(t.status).label}}
            </span>
            <span class="tc-version">v{{t.version}}</span>
          </div>
          <div class="tc-title">{{t.title}}</div>
          <div class="tc-meta">
            <span *ngIf="t.sector" class="tc-sector">{{t.sector}}</span>
            <span class="tc-count">{{t.questions_count || 0}} preguntas</span>
          </div>
          <div class="tc-actions" (click)="$event.stopPropagation()">
            <button mat-icon-button matTooltip="Abrir editor" (click)="openTemplate(t)">
              <mat-icon>open_in_new</mat-icon>
            </button>
            <button mat-icon-button [matMenuTriggerFor]="cardMenu" matTooltip="Mas opciones">
              <mat-icon>more_vert</mat-icon>
            </button>
            <mat-menu #cardMenu="matMenu">
              <button mat-menu-item *ngIf="t.status === 'draft'" (click)="onPublish(t)">
                <mat-icon>publish</mat-icon><span>Publicar</span>
              </button>
              <button mat-menu-item *ngIf="t.status === 'published'" (click)="onNewVersion(t)">
                <mat-icon>content_copy</mat-icon><span>Nueva version</span>
              </button>
              <button mat-menu-item *ngIf="t.status !== 'archived'" (click)="onArchive(t)">
                <mat-icon>archive</mat-icon><span>Archivar</span>
              </button>
              <mat-divider></mat-divider>
              <button mat-menu-item *ngIf="t.status !== 'published'" (click)="onDelete(t)" class="menu-danger">
                <mat-icon color="warn">delete</mat-icon><span>Eliminar</span>
              </button>
            </mat-menu>
          </div>
        </div>
      </div>

      <!-- Editor de plantilla -->
      <div *ngIf="selectedTemplate" class="editor-wrap">
        <div class="editor-toolbar glass-card">
          <button mat-icon-button matTooltip="Volver" (click)="closeEditor()">
            <mat-icon>arrow_back</mat-icon>
          </button>
          <div class="editor-title-wrap">
            <span class="editor-title">{{selectedTemplate.title}}</span>
            <span class="tc-status-label" [style.color]="statusFor(selectedTemplate.status).color">
              {{statusFor(selectedTemplate.status).label}} &mdash; v{{selectedTemplate.version}}
            </span>
          </div>
          <div style="flex:1"></div>
          <button mat-stroked-button *ngIf="selectedTemplate.status === 'draft'" (click)="onPublish(selectedTemplate)">
            <mat-icon>publish</mat-icon> Publicar
          </button>
          <button mat-stroked-button *ngIf="selectedTemplate.status === 'published'" (click)="onNewVersion(selectedTemplate)">
            <mat-icon>content_copy</mat-icon> Nueva version
          </button>
        </div>

        <!-- Info de la plantilla -->
        <mat-expansion-panel class="glass-card" style="margin-bottom:16px">
          <mat-expansion-panel-header>
            <mat-panel-title>Informacion de la plantilla</mat-panel-title>
          </mat-expansion-panel-header>
          <form [formGroup]="templateForm" (ngSubmit)="saveTemplateInfo()" class="zia-form-compact">
            <mat-form-field appearance="outline">
              <mat-label>Titulo</mat-label>
              <input matInput formControlName="title">
            </mat-form-field>
            <mat-form-field appearance="outline">
              <mat-label>Sector objetivo (opcional)</mat-label>
              <input matInput formControlName="sector" placeholder="Ej: Manufactura, Transporte...">
            </mat-form-field>
            <mat-form-field appearance="outline">
              <mat-label>Descripcion</mat-label>
              <textarea matInput formControlName="description" rows="2"></textarea>
            </mat-form-field>
            <div style="text-align:right">
              <button mat-flat-button color="primary" type="submit" [disabled]="templateForm.invalid || selectedTemplate.status === 'published'">
                Guardar cambios
              </button>
            </div>
          </form>
        </mat-expansion-panel>

        <!-- Lista de preguntas -->
        <div class="glass-card questions-panel">
          <div class="qp-header">
            <span class="qp-title">Preguntas ({{questions.length}})</span>
            <button mat-flat-button class="btn-prestige" (click)="onAddQuestion()" [disabled]="selectedTemplate.status === 'published'">
              <mat-icon>add</mat-icon> Agregar pregunta
            </button>
          </div>

          <div class="empty-qs" *ngIf="questions.length === 0">
            <mat-icon>quiz</mat-icon>
            <p>Agrega la primera pregunta a esta plantilla.</p>
          </div>

          <div *ngFor="let q of questions; let i = index" class="question-item" [class.published]="selectedTemplate.status === 'published'">
            <div class="q-index">{{i + 1}}</div>
            <div class="q-body">
              <div class="q-text">{{q.question_text}}</div>
              <div class="q-meta">
                <span class="q-type-badge">{{typeLabel(q.question_type)}}</span>
                <span *ngIf="q.scope_hint" class="q-scope-badge scope-{{q.scope_hint}}">{{q.scope_hint.replace('_',' ')}}</span>
                <span *ngIf="q.unit" class="q-unit">{{q.unit}}</span>
                <span *ngIf="q.required" class="q-req">*Requerida</span>
              </div>
              <div *ngIf="q.help_text" class="q-help">{{q.help_text}}</div>
            </div>
            <div class="q-actions" *ngIf="selectedTemplate.status !== 'published'">
              <button mat-icon-button matTooltip="Editar" (click)="onEditQuestion(q)">
                <mat-icon>edit</mat-icon>
              </button>
              <button mat-icon-button matTooltip="Eliminar" (click)="onDeleteQuestion(q)">
                <mat-icon>delete</mat-icon>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Panel lateral: formulario de pregunta -->
      <div *ngIf="showQuestionForm" class="question-form-overlay">
        <div class="question-form-panel glass-card">
          <div class="qfp-header">
            <span>{{editingQuestion?.id ? 'Editar pregunta' : 'Nueva pregunta'}}</span>
            <button mat-icon-button (click)="cancelQuestion()"><mat-icon>close</mat-icon></button>
          </div>
          <form [formGroup]="questionForm" (ngSubmit)="saveQuestion()" class="zia-form-compact">
            <mat-form-field appearance="outline">
              <mat-label>Texto de la pregunta</mat-label>
              <textarea matInput formControlName="question_text" rows="2" placeholder="Ej: Cuantos litros de diesel consumio en enero?"></textarea>
            </mat-form-field>

            <div class="form-row">
              <mat-form-field appearance="outline">
                <mat-label>Tipo de respuesta</mat-label>
                <mat-select formControlName="question_type">
                  <mat-option *ngFor="let t of questionTypes" [value]="t.value">{{t.label}}</mat-option>
                </mat-select>
              </mat-form-field>

              <mat-form-field appearance="outline" *ngIf="questionForm.value.question_type === 'number'">
                <mat-label>Unidad (opcional)</mat-label>
                <input matInput formControlName="unit" placeholder="Ej: L, kWh, t">
              </mat-form-field>
            </div>

            <div class="form-row">
              <mat-form-field appearance="outline">
                <mat-label>Alcance sugerido</mat-label>
                <mat-select formControlName="scope_hint">
                  <mat-option *ngFor="let s of scopeHints" [value]="s.value">{{s.label}}</mat-option>
                </mat-select>
              </mat-form-field>

              <mat-form-field appearance="outline">
                <mat-label>Categoria GHG (opcional)</mat-label>
                <input matInput formControlName="category_hint" placeholder="Ej: Combustion estacionaria">
              </mat-form-field>
            </div>

            <mat-form-field appearance="outline">
              <mat-label>Texto de ayuda (opcional)</mat-label>
              <input matInput formControlName="help_text" placeholder="Instruccion o aclaracion para el usuario">
            </mat-form-field>

            <div class="form-toggle-row">
              <mat-slide-toggle formControlName="required" color="primary">Respuesta obligatoria</mat-slide-toggle>
            </div>

            <div style="text-align:right;margin-top:16px;display:flex;gap:8px;justify-content:flex-end">
              <button mat-button type="button" (click)="cancelQuestion()">Cancelar</button>
              <button mat-flat-button color="primary" type="submit" [disabled]="questionForm.invalid">
                {{editingQuestion?.id ? 'Guardar' : 'Agregar'}}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  `,
    styles: [`
    .management-page { padding: 24px; max-width: 1400px; margin: 0 auto; }
    .header-section { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; gap: 20px; }
    .title-group h1 { font-size: 28px; font-weight: 600; color: var(--prestige-primary); margin: 0 0 4px 0; letter-spacing: -0.02em; }
    .subtitle { color: var(--prestige-text-muted); margin: 0; font-size: 14px; }
    .btn-prestige { background: var(--prestige-primary); color: white; padding: 0 20px; border-radius: 10px; font-weight: 500; height: 42px; font-size: 14px; }
    .spinner-wrap { text-align: center; padding: 48px; }
    .empty-card { padding: 48px; text-align: center; color: var(--prestige-text-muted); display: flex; flex-direction: column; align-items: center; gap: 12px; }
    .empty-card mat-icon { font-size: 48px; width: 48px; height: 48px; opacity: .3; }

    /* Templates grid */
    .templates-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
    .template-card { padding: 20px; cursor: pointer; transition: transform .15s, box-shadow .15s; position: relative; }
    .template-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.1); }
    .tc-header { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
    .tc-status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .tc-status-label { font-size: 11px; font-weight: 600; display: flex; align-items: center; gap: 3px; }
    .tc-version { margin-left: auto; font-size: 11px; color: var(--prestige-text-muted); background: var(--prestige-border); padding: 2px 6px; border-radius: 4px; }
    .tc-title { font-size: 16px; font-weight: 700; color: var(--prestige-text); margin-bottom: 8px; line-height: 1.3; }
    .tc-meta { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .tc-sector { font-size: 11px; background: rgba(26,35,126,.08); color: #1a237e; padding: 2px 8px; border-radius: 12px; font-weight: 600; }
    .tc-count { font-size: 11px; color: var(--prestige-text-muted); }
    .tc-actions { display: flex; gap: 2px; position: absolute; top: 12px; right: 12px; }

    /* Editor */
    .editor-wrap { display: flex; flex-direction: column; gap: 16px; }
    .editor-toolbar { padding: 12px 20px; display: flex; align-items: center; gap: 12px; }
    .editor-title-wrap { display: flex; flex-direction: column; gap: 2px; }
    .editor-title { font-size: 18px; font-weight: 700; color: var(--prestige-text); }

    /* Questions */
    .questions-panel { padding: 0; overflow: hidden; }
    .qp-header { padding: 20px 24px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--prestige-border); }
    .qp-title { font-size: 15px; font-weight: 700; color: var(--prestige-text); }
    .empty-qs { padding: 48px; text-align: center; color: var(--prestige-text-muted); }
    .empty-qs mat-icon { font-size: 40px; width: 40px; height: 40px; display: block; margin: 0 auto 12px; opacity: .3; }
    .question-item { display: flex; align-items: flex-start; gap: 16px; padding: 16px 24px; border-bottom: 1px solid var(--prestige-border); transition: background .1s; }
    .question-item:hover { background: var(--row-hover-bg); }
    .question-item.published .q-actions { display: none; }
    .q-index { width: 28px; height: 28px; border-radius: 50%; background: rgba(26,35,126,.08); color: #1a237e; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; margin-top: 2px; }
    .q-body { flex: 1; min-width: 0; }
    .q-text { font-size: 14px; font-weight: 600; color: var(--prestige-text); margin-bottom: 6px; }
    .q-meta { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .q-type-badge { font-size: 10px; font-weight: 700; background: rgba(26,35,126,.08); color: #1a237e; padding: 2px 7px; border-radius: 4px; text-transform: uppercase; letter-spacing: .04em; }
    .q-scope-badge { font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 4px; text-transform: uppercase; }
    .scope-scope_1 { background: #dbeafe; color: #1d4ed8; }
    .scope-scope_2 { background: #d1fae5; color: #065f46; }
    .scope-scope_3 { background: #fef3c7; color: #92400e; }
    .q-unit { font-size: 11px; color: var(--prestige-text-muted); padding: 2px 7px; background: var(--prestige-border); border-radius: 4px; }
    .q-req { font-size: 11px; color: #dc2626; font-weight: 600; }
    .q-help { font-size: 12px; color: var(--prestige-text-muted); margin-top: 4px; font-style: italic; }
    .q-actions { display: flex; gap: 2px; flex-shrink: 0; }

    /* Question form panel */
    .question-form-overlay { position: fixed; top: 0; right: 0; bottom: 0; width: 420px; z-index: 1000; background: rgba(0,0,0,.35); display: flex; align-items: stretch; }
    .question-form-panel { width: 420px; padding: 24px; overflow-y: auto; border-radius: 0; height: 100%; }
    .qfp-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; font-size: 16px; font-weight: 700; color: var(--prestige-text); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .form-toggle-row { margin: 8px 0; }
    .zia-form-compact { display: flex; flex-direction: column; gap: 12px; }
    .menu-danger { color: var(--mat-warn-color); }
  `]
})
export class QuestionnaireMgmtComponent implements OnInit {
    private adminService = inject(AdminService);
    private fb = inject(FormBuilder);
    private cdr = inject(ChangeDetectorRef);

    templates: any[] = [];
    loading = true;
    selectedTemplate: any = null;
    questions: any[] = [];

    templateForm!: FormGroup;
    questionForm!: FormGroup;
    showQuestionForm = false;
    editingQuestion: any = null;

    readonly questionTypes = QUESTION_TYPES;
    readonly scopeHints = SCOPE_HINTS;

    ngOnInit() {
        this.loadTemplates();
    }

    statusFor(status: string) {
        return STATUS_LABEL[status] || STATUS_LABEL['draft'];
    }

    typeLabel(type: string) {
        return QUESTION_TYPES.find(t => t.value === type)?.label || type;
    }

    loadTemplates() {
        this.loading = true;
        this.adminService.getQuestionnaires().subscribe({
            next: (data) => { this.templates = data; this.loading = false; this.cdr.detectChanges(); },
            error: () => { this.loading = false; this.cdr.detectChanges(); }
        });
    }

    openTemplate(t: any) {
        this.adminService.getQuestionnaire(t.id).subscribe(data => {
            this.selectedTemplate = data;
            this.questions = data.questions || [];
            this.templateForm = this.fb.group({
                title:       [data.title, Validators.required],
                sector:      [data.sector || ''],
                description: [data.description || ''],
            });
            this.cdr.detectChanges();
        });
    }

    closeEditor() {
        this.selectedTemplate = null;
        this.showQuestionForm = false;
        this.loadTemplates();
    }

    onCreateTemplate() {
        const title = prompt('Titulo de la plantilla:');
        if (!title?.trim()) return;
        this.adminService.createQuestionnaire({ title: title.trim() }).subscribe(data => {
            this.templates.unshift({ ...data, questions_count: 0 });
            this.openTemplate(data);
        });
    }

    saveTemplateInfo() {
        if (!this.selectedTemplate || this.templateForm.invalid) return;
        this.adminService.updateQuestionnaire(this.selectedTemplate.id, this.templateForm.value).subscribe(data => {
            this.selectedTemplate = { ...this.selectedTemplate, ...data };
            this.cdr.detectChanges();
        });
    }

    onPublish(t: any) {
        this.adminService.publishQuestionnaire(t.id).subscribe(() => {
            if (this.selectedTemplate?.id === t.id) {
                this.selectedTemplate.status = 'published';
            }
            this.loadTemplates();
        });
    }

    onArchive(t: any) {
        this.adminService.archiveQuestionnaire(t.id).subscribe(() => {
            if (this.selectedTemplate?.id === t.id) {
                this.selectedTemplate.status = 'archived';
            }
            this.loadTemplates();
        });
    }

    onNewVersion(t: any) {
        this.adminService.newQuestionnaireVersion(t.id).subscribe(data => {
            this.loadTemplates();
            this.openTemplate(data);
        });
    }

    onDelete(t: any) {
        if (!confirm(`Eliminar la plantilla "${t.title}"?`)) return;
        this.adminService.deleteQuestionnaire(t.id).subscribe(() => {
            this.templates = this.templates.filter(x => x.id !== t.id);
            if (this.selectedTemplate?.id === t.id) this.closeEditor();
            this.cdr.detectChanges();
        });
    }

    // Questions
    onAddQuestion() {
        this.editingQuestion = null;
        this.questionForm = this.buildQuestionForm({});
        this.showQuestionForm = true;
    }

    onEditQuestion(q: any) {
        this.editingQuestion = q;
        this.questionForm = this.buildQuestionForm(q);
        this.showQuestionForm = true;
    }

    buildQuestionForm(q: any): FormGroup {
        return this.fb.group({
            question_text: [q.question_text || '', Validators.required],
            question_type: [q.question_type || 'text', Validators.required],
            unit:          [q.unit || ''],
            scope_hint:    [q.scope_hint || ''],
            category_hint: [q.category_hint || ''],
            help_text:     [q.help_text || ''],
            required:      [q.required !== undefined ? q.required : true],
            order:         [q.order || this.questions.length],
        });
    }

    saveQuestion() {
        if (!this.questionForm.valid || !this.selectedTemplate) return;
        const val = this.questionForm.value;

        if (this.editingQuestion?.id) {
            this.adminService.updateQuestion(this.selectedTemplate.id, this.editingQuestion.id, val).subscribe(updated => {
                const idx = this.questions.findIndex(q => q.id === this.editingQuestion.id);
                if (idx !== -1) this.questions[idx] = updated;
                this.showQuestionForm = false;
                this.cdr.detectChanges();
            });
        } else {
            this.adminService.addQuestion(this.selectedTemplate.id, val).subscribe(created => {
                this.questions.push(created);
                this.showQuestionForm = false;
                this.cdr.detectChanges();
            });
        }
    }

    cancelQuestion() {
        this.showQuestionForm = false;
        this.editingQuestion = null;
    }

    onDeleteQuestion(q: any) {
        if (!confirm('Eliminar esta pregunta?')) return;
        this.adminService.deleteQuestion(this.selectedTemplate.id, q.id).subscribe(() => {
            this.questions = this.questions.filter(x => x.id !== q.id);
            this.cdr.detectChanges();
        });
    }
}

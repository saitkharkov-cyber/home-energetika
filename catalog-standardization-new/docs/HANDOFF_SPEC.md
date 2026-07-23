# HANDOFF SPEC — catalog-standardization-new

`docs/HANDOFF.md` — временная межсессионная память. Во время активной сессии его отсутствие нормально. Новый `HANDOFF.md` создаётся только при закрытии или переносе сессии.

Handoff не является полным master summary, status-документом, changelog, backlog, архивом или roadmap. Он должен быть компактным: постоянные подробности заменяются ссылками на постоянные документы.

## Обязательное содержимое

Каждый handoff содержит:

1. дату;
2. repository;
3. local path;
4. working area;
5. branch;
6. session close base commit;
7. repository snapshot;
8. текущего производителя или другой target;
9. scope и stage;
10. выполненное в сессии;
11. открытые вопросы;
12. gates и safety;
13. protected user-owned changes;
14. остановленные или отклонённые пути;
15. ссылки на постоянные документы;
16. ровно один следующий bounded step;
17. действия нового чата после чтения.

## Ограничения и gates

1. Handoff не даёт authorization на file changes, commit, push, DB, parser run, matching, SQL/apply, production/cache или следующий engineering step.
2. Создание HANDOFF.md, transport commit и push требуют отдельного пользовательского +; cleanup после успешного восстановления также требует отдельного +.
3. Перед handoff project-scope работа должна быть committed и pushed. Незакоммиченными могут оставаться только явно классифицированные protected user-owned changes вне scope.
4. Session close base commit — это HEAD непосредственно перед transport commit с HANDOFF.md. Transport SHA внутрь handoff не записывается.
5. Перед созданием handoff CURRENT_OVERRIDE.md, если он существовал, должен быть закрыт; актуальные сведения перенесены в постоянные документы, а сам файл удалён.
6. Handoff не содержит secrets, production credentials, raw terminal transcript, полный diff, большие CSV/SQL outputs, несколько следующих шагов, неподтверждённые догадки или project-scope uncommitted changes.
7. Отдельно фиксируй применимые gates: DB read-only, parser run, matching preview, SQL preview, production SQL/apply, post-apply verification, cache rebuild, commit/push.
8. Перед documentation commit выполняй git diff --check и git status --short.
9. После успешного восстановления контекста новый чат сообщает, что handoff выполнил функцию.
10. Удаление handoff, commit удаления и следующий engineering step требуют отдельных пользовательских разрешений.

## Validation checklist

- [ ] Project-scope работа committed и pushed; protected user-owned changes вне scope явно классифицированы.
- [ ] Session close base commit указывает на HEAD перед transport commit; transport SHA отсутствует.
- [ ] CURRENT_OVERRIDE.md закрыт, постоянные сведения перенесены, файл удалён.
- [ ] Указан ровно один следующий bounded step и нет запрещённых данных или raw outputs.
- [ ] Создание, transport commit/push и будущий cleanup имеют отдельные пользовательские разрешения.

## Минимальный шаблон

```markdown
# Session handoff

- Date:
- Repository:
- Local path:
- Working area:
- Branch:
- Session close base commit:
- Repository snapshot:
- Current target:
- Scope and stage:
- Completed this session:
- Open questions:
- Gates and safety:
- Protected user-owned changes:
- Stopped or rejected paths:
- Permanent documents:
- One next bounded step:
- New-chat actions after reading:
```

---

Последнее обновление: 2026-07-23 21:11:44 Europe/Kyiv

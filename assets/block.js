(function (blocks, element, components, blockEditor, apiFetch) {
    var el = element.createElement;
    var useEffect = element.useEffect;
    var useState = element.useState;
    var SelectControl = components.SelectControl;
    var Button = components.Button;
    var Notice = components.Notice;
    var Spinner = components.Spinner;
    var PanelBody = components.PanelBody;
    var InspectorControls = blockEditor.InspectorControls;

    function MatchPreview(props) {
        if (!props.item) {
            return null;
        }

        var rows = (props.item.parties || []).map(function (partie, index) {
            var sets = Array.isArray(partie.setDetails) ? partie.setDetails : [];
            var setsNode = sets.length
                ? el('span', { className: 'fftt-sets' }, sets.map(function (setScore, setIndex) {
                    return el('span', { className: 'fftt-set-score', key: String(index) + '-set-' + String(setIndex) }, String(setScore));
                }))
                : el('span', { className: 'fftt-set-score' }, '-');

            var playerA = partie.playerA;
            var playerB = partie.playerB;

            if (partie.winnerSide === 'A') {
                playerA = el('strong', {}, partie.playerA);
            }

            if (partie.winnerSide === 'B') {
                playerB = el('strong', {}, partie.playerB);
            }

            return el('tr', { key: index }, [
                el('td', {}, playerA),
                el('td', {}, String(partie.scoreA) + ' - ' + String(partie.scoreB)),
                el('td', {}, playerB),
                el('td', {}, setsNode)
            ]);
        });

        return el('div', { className: 'fftt-match-block preview' }, [
            el('div', { className: 'fftt-match-score' }, [
                el('span', { className: 'fftt-team' }, props.item.teamA),
                el('strong', { className: 'fftt-score' }, String(props.item.scoreA) + ' - ' + String(props.item.scoreB)),
                el('span', { className: 'fftt-team' }, props.item.teamB)
            ]),
            el('table', { className: 'fftt-parties-table' }, [
                el('thead', {}, el('tr', {}, [
                    el('th', {}, 'Joueur 1'),
                    el('th', {}, 'Score'),
                    el('th', {}, 'Joueur 2'),
                    el('th', {}, 'Details des sets')
                ])),
                el('tbody', {}, rows)
            ])
        ]);
    }

    blocks.registerBlockType('fftt/match', {
        title: 'FFTT Match',
        icon: 'table-col-after',
        category: 'widgets',
        attributes: {
            teamId: { type: 'number', default: 0 },
            teamName: { type: 'string', default: '' },
            matchLink: { type: 'string', default: '' },
            matchLabel: { type: 'string', default: '' },
            matchClubA: { type: 'string', default: '' },
            matchClubB: { type: 'string', default: '' }
        },

        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var teams = useState([]);
            var matches = useState([]);
            var isLoadingTeams = useState(true);
            var isLoadingMatches = useState(false);
            var error = useState('');
            var preview = useState(null);
            var previewLoading = useState(false);

            var teamsValue = teams[0];
            var setTeams = teams[1];
            var matchesValue = matches[0];
            var setMatches = matches[1];
            var loadingTeamsValue = isLoadingTeams[0];
            var setLoadingTeams = isLoadingTeams[1];
            var loadingMatchesValue = isLoadingMatches[0];
            var setLoadingMatches = isLoadingMatches[1];
            var errorValue = error[0];
            var setError = error[1];
            var previewValue = preview[0];
            var setPreview = preview[1];
            var previewLoadingValue = previewLoading[0];
            var setPreviewLoading = previewLoading[1];

            function loadTeams() {
                setLoadingTeams(true);
                setError('');
                apiFetch({ path: '/fftt-match/v1/teams' }).then(function (response) {
                    setTeams(response.items || []);
                    setLoadingTeams(false);
                }).catch(function (err) {
                    setError(err && err.message ? err.message : 'Erreur lors du chargement des equipes.');
                    setTeams([]);
                    setLoadingTeams(false);
                });
            }

            function loadMatches(teamId) {
                if (!teamId) {
                    setMatches([]);
                    return;
                }

                setLoadingMatches(true);
                setError('');

                var path = '/fftt-match/v1/matches?teamId=' + encodeURIComponent(String(teamId));

                apiFetch({ path: path }).then(function (response) {
                    var items = Array.isArray(response.items) ? response.items.slice() : [];
                    items.sort(function (left, right) {
                        var leftTs = left && left.date ? Date.parse(left.date) : NaN;
                        var rightTs = right && right.date ? Date.parse(right.date) : NaN;

                        if (Number.isNaN(leftTs) && Number.isNaN(rightTs)) {
                            return 0;
                        }
                        if (Number.isNaN(leftTs)) {
                            return 1;
                        }
                        if (Number.isNaN(rightTs)) {
                            return -1;
                        }

                        return rightTs - leftTs;
                    });

                    setMatches(items);
                    setLoadingMatches(false);
                }).catch(function (err) {
                    setError(err && err.message ? err.message : 'Erreur lors du chargement des matchs.');
                    setMatches([]);
                    setLoadingMatches(false);
                });
            }

            function loadPreview(link, clubA, clubB) {
                if (!link || !clubA || !clubB) {
                    setPreview(null);
                    return;
                }

                setPreviewLoading(true);
                var path = '/fftt-match/v1/match-details?lien=' + encodeURIComponent(link)
                    + '&clubA=' + encodeURIComponent(clubA)
                    + '&clubB=' + encodeURIComponent(clubB);

                apiFetch({ path: path })
                    .then(function (response) {
                        setPreview(response.item || null);
                        setPreviewLoading(false);
                    })
                    .catch(function (err) {
                        setError(err && err.message ? err.message : 'Erreur preview.');
                        setPreview(null);
                        setPreviewLoading(false);
                    });
            }

            useEffect(function () {
                loadTeams();
            }, []);

            useEffect(function () {
                loadMatches(attributes.teamId || 0);
            }, [attributes.teamId]);

            useEffect(function () {
                loadPreview(attributes.matchLink, attributes.matchClubA, attributes.matchClubB);
            }, [attributes.matchLink, attributes.matchClubA, attributes.matchClubB]);

            var teamOptions = [{ label: 'Selectionner une equipe', value: 0 }].concat(
                teamsValue.map(function (item) {
                    return {
                        label: item.name,
                        value: item.id
                    };
                })
            );

            var options = [{ label: 'Selectionner un match', value: '' }].concat(
                matchesValue.map(function (item) {
                    return {
                        label: item.label,
                        value: item.lien
                    };
                })
            );

            return el('div', { className: props.className }, [
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Selection du match', initialOpen: true }, [
                        el(SelectControl, {
                            label: 'Equipe FFTT',
                            value: attributes.teamId || 0,
                            options: teamOptions,
                            onChange: function (value) {
                                var nextTeamId = Number(value) || 0;
                                var nextTeamName = '';
                                for (var t = 0; t < teamsValue.length; t += 1) {
                                    if (Number(teamsValue[t].id) === nextTeamId) {
                                        nextTeamName = teamsValue[t].name;
                                        break;
                                    }
                                }

                                setAttributes({
                                    teamId: nextTeamId,
                                    teamName: nextTeamName,
                                    matchLink: '',
                                    matchLabel: '',
                                    matchClubA: '',
                                    matchClubB: ''
                                });
                                setPreview(null);
                            }
                        }),
                        el(SelectControl, {
                            label: 'Match FFTT',
                            value: attributes.matchLink,
                            options: options,
                            onChange: function (value) {
                                var label = '';
                                var clubA = '';
                                var clubB = '';
                                for (var i = 0; i < matchesValue.length; i += 1) {
                                    if (matchesValue[i].lien === value) {
                                        label = matchesValue[i].label;
                                        clubA = String(matchesValue[i].clubA || '');
                                        clubB = String(matchesValue[i].clubB || '');
                                        break;
                                    }
                                }
                                setAttributes({
                                    matchLink: value,
                                    matchLabel: label,
                                    matchClubA: clubA,
                                    matchClubB: clubB
                                });
                            }
                        }),
                        el(Button, {
                            variant: 'secondary',
                            onClick: function () {
                                loadTeams();
                                loadMatches(attributes.teamId || 0);
                            }
                        }, 'Rafraichir equipes et matchs')
                    ])
                ),
                loadingTeamsValue ? el(Spinner) : null,
                loadingMatchesValue ? el(Spinner) : null,
                errorValue ? el(Notice, { status: 'error', isDismissible: false }, errorValue) : null,
                !attributes.teamId ? el('p', {}, 'Choisis une equipe dans la colonne de droite.') : null,
                attributes.teamId && !attributes.matchLink ? el('p', {}, 'Choisis un match dans la colonne de droite.') : null,
                previewLoadingValue ? el(Spinner) : null,
                el(MatchPreview, { item: previewValue })
            ]);
        },

        save: function () {
            return null;
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.apiFetch);
